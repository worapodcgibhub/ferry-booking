<?php

/**
 * ทดสอบการแย่งที่นั่งแบบหลายโปรเซสพร้อมกัน (ต้องใช้ MariaDB/MySQL จริง — SQLite ในหน่วยความจำใช้ข้ามโปรเซสไม่ได้)
 *
 * สถานการณ์ (--scenario=single|roundtrip|mixed|all, ค่าเริ่มต้น all):
 *   single     เที่ยวเดียวแย่งที่นั่งสุดท้าย 1 ที่ → สำเร็จ 1 รายการ ที่เหลือถูกปฏิเสธ
 *   roundtrip  (ก) ไป–กลับหลายรายการพร้อมกันบนรอบเรือชุดเดียวกัน ที่นั่งสุดท้ายของ "ขากลับ" (รอบคี่) หรือ "ขาไป" (รอบคู่)
 *              เหลือ 1 ที่ → สำเร็จ 1 รายการ และต้องไม่มีที่นั่งของอีกขาค้าง (ขาที่ไม่เต็มต้องมี seats_taken = 1 พอดี)
 *   mixed      (ข) งานผสมที่ล็อกรอบเรือสลับลำดับกัน: จองไป–กลับสุ่มคู่รอบ (id ของรอบขาไป/ขากลับสลับกันมากน้อยต่างกัน)
 *              + ยกเลิก + คืนที่นั่งรายการหมดเวลา พร้อมกัน → ต้องไม่ deadlock/error ที่ไม่คาดคิด,
 *              และหลังจบ seats_taken ของทุกรอบเรือต้องเท่ากับผลรวมที่นั่งของการจองที่ยังไม่ถูกคืน (ไม่เกินโควตา ไม่ติดลบ)
 *
 * วิธีรัน (ตัวอย่าง PowerShell — ดู README):
 *   $env:DB_CONNECTION='mariadb'; $env:DB_DATABASE='ferry_booking_test'; $env:DB_USERNAME='...'; $env:DB_PASSWORD='...'
 *   php scripts/race-last-seat.php --scenario=all --processes=8 --rounds=20
 *
 * ⚠ สคริปต์นี้ล้างตารางทั้งฐาน (migrate:fresh) จึงปฏิเสธการรันถ้าชื่อฐานข้อมูลไม่ลงท้ายด้วย "_test"
 * exit code: 0 = ผ่านทุกรอบ, 1 = มีรอบไม่ผ่าน, 2 = ไม่ได้รัน (ตั้งค่าไม่ปลอดภัย)
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingLeg;
use App\Models\Island;
use App\Models\Port;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

$opts = getopt('', ['child::', 'job:', 'processes::', 'rounds::', 'scenario::']);

// =====================================================================================
// โหมดลูก: ทำรายการตามลำดับใน job หลังถึงเวลาเริ่มร่วม แล้วพิมพ์ผล JSON บรรทัดสุดท้าย
// =====================================================================================
if (isset($opts['child'])) {
    $job = json_decode(base64_decode($opts['job']), true);
    while (microtime(true) < $job['start']) {
        usleep(200);
    }

    $rollbacks = 0;
    Event::listen(TransactionRolledBack::class, function () use (&$rollbacks) {
        $rollbacks++;
    });
    $svc = app(BookingService::class);
    $staff = User::find($job['staff']);
    $out = [];

    foreach ($job['ops'] as $op) {
        $before = $rollbacks;
        $res = ['type' => $op['type']];
        try {
            switch ($op['type']) {
                case 'create':
                    $b = $svc->create([
                        'outbound_trip_id' => $op['out'], 'return_trip_id' => $op['ret'] ?? null, 'passengers' => $op['pax'],
                        'contact_name' => 'race', 'contact_phone' => '0811111111', 'contact_email' => 'race@example.test',
                        'idempotency_key' => $op['key'],
                    ]);
                    $res['r'] = 'OK';
                    $res['ref'] = $b->reference;
                    break;
                case 'cancel':
                    $svc->cancel(Booking::findOrFail($op['id']), $staff, 'race');
                    $res['r'] = 'OK';
                    break;
                case 'expire':
                    $res['r'] = 'OK';
                    $res['n'] = $svc->expireDue();
                    break;
            }
        } catch (BookingException) {
            $res['r'] = 'REJECT'; // ที่นั่งเต็ม/ทำรายการไม่ได้ตามกติกา (ปฏิเสธอย่างสุภาพ)
        } catch (Throwable $e) {
            $res['r'] = 'ERROR';
            $res['m'] = get_class($e).': '.str_replace(["\r", "\n"], ' ', substr($e->getMessage(), 0, 160));
        }
        // ธุรกรรมที่ rollback เกินที่คาด = มีการชนกันแล้ว retry (create ที่ถูกปฏิเสธมี rollback ปกติ 1 ครั้ง)
        $expected = ($op['type'] === 'create' && $res['r'] === 'REJECT') ? 1 : 0;
        $res['retries'] = max(0, ($rollbacks - $before) - $expected);
        $out[] = $res;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE).PHP_EOL;
    exit(0);
}

// =====================================================================================
// โหมดหลัก
// =====================================================================================
$processes = max(2, (int) ($opts['processes'] ?? 8));
$rounds = max(1, (int) ($opts['rounds'] ?? 20));
$scenario = (string) ($opts['scenario'] ?? 'all');
$driver = DB::connection()->getDriverName();
$database = (string) DB::connection()->getDatabaseName();

if (! in_array($scenario, ['single', 'roundtrip', 'mixed', 'all'], true)) {
    fwrite(STDERR, "scenario ต้องเป็น single | roundtrip | mixed | all\n");
    exit(2);
}
if (app()->environment('production') || ! in_array($driver, ['mariadb', 'mysql'], true) || ! str_ends_with($database, '_test')) {
    fwrite(STDERR, "ปฏิเสธการรัน: ต้องใช้ MariaDB/MySQL ที่ชื่อฐานข้อมูลลงท้าย _test (ตอนนี้: {$driver} / {$database})\n"
        ."ตั้งค่าด้วย DB_CONNECTION, DB_DATABASE (เช่น ferry_booking_test), DB_USERNAME, DB_PASSWORD แล้วรันใหม่ — ดูหัวไฟล์\n");
    exit(2);
}

echo "ฐานข้อมูลทดสอบ: {$driver}/{$database} · โปรเซสต่อรอบ: {$processes} · จำนวนรอบ: {$rounds} · สถานการณ์: {$scenario}\n";
Artisan::call('migrate:fresh', ['--force' => true]);

$staff = User::create(['name' => 'race staff', 'email' => 'race-staff@example.test', 'password' => 'not-used-'.bin2hex(random_bytes(6)), 'role' => 'staff']);
$island = Island::create(['name' => 'เกาะทดสอบแข่งขัน', 'slug' => 'race']);
$portA = Port::create(['name' => 'ท่า A']);
$portB = Port::create(['name' => 'ท่า B', 'island_id' => $island->id]);
$r1 = Route::create(['origin_port_id' => $portA->id, 'destination_port_id' => $portB->id, 'island_id' => $island->id, 'duration_minutes' => 60]); // ขาไป
$r2 = Route::create(['origin_port_id' => $portB->id, 'destination_port_id' => $portA->id, 'island_id' => $island->id, 'duration_minutes' => 60]); // ขากลับ

function makeTrip(Route $route, Carbon $departs, int $capacity): Trip
{
    return Trip::create(['route_id' => $route->id, 'departs_at' => $departs, 'arrives_at' => $departs->copy()->addHour(), 'price' => 450, 'capacity' => $capacity]);
}

/** สั่งโปรเซสลูกหนึ่งตัวต่อ job พร้อมกัน คืนผลของแต่ละตัว (list ของผลต่อ op) หรือสตริงข้อผิดพลาด */
function spawnAll(array $jobs, int $staffId): array
{
    $start = microtime(true) + 1.8; // เวลาเริ่มร่วม ให้ทุกโปรเซสบูตเสร็จก่อนแล้วยิงพร้อมกัน
    $handles = [];
    foreach ($jobs as $job) {
        $arg = base64_encode(json_encode($job + ['start' => $start, 'staff' => $staffId]));
        $proc = proc_open([PHP_BINARY, __FILE__, '--child=1', '--job='.$arg], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $handles[] = [$proc, $pipes];
    }
    $results = [];
    foreach ($handles as [$proc, $pipes]) {
        $stdout = trim((string) stream_get_contents($pipes[1]));
        $stderr = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $lines = $stdout === '' ? [] : preg_split('/\R/', $stdout);
        $decoded = $lines ? json_decode((string) end($lines), true) : null;
        $results[] = is_array($decoded) ? $decoded : 'ลูกไม่ส่งผลกลับ: '.substr($stdout.' '.$stderr, 0, 200);
    }

    return $results;
}

/** รวมผลของทุกโปรเซส → [ok, reject, error, retries, deadlockErrors, errors[], crashed[]] */
function tally(array $results): array
{
    $t = ['ok' => 0, 'reject' => 0, 'error' => 0, 'retries' => 0, 'deadlock' => 0, 'errors' => [], 'crashed' => []];
    foreach ($results as $res) {
        if (! is_array($res)) {
            $t['crashed'][] = $res;
            continue;
        }
        foreach ($res as $r) {
            $t['retries'] += $r['retries'] ?? 0;
            match ($r['r']) {
                'OK' => $t['ok']++,
                'REJECT' => $t['reject']++,
                default => (function () use (&$t, $r) {
                    $t['error']++;
                    $t['errors'][] = $r['m'] ?? '?';
                    if (preg_match('/deadlock|1213|1020|40001/i', $r['m'] ?? '')) {
                        $t['deadlock']++;
                    }
                })(),
            };
        }
    }

    return $t;
}

$summary = [];
$allPassed = true;
$failureNotes = [];

// ---------------------------------- single ----------------------------------
function runSingle(int $round, int $processes, Route $r1, int $staffId): array
{
    $trip = makeTrip($r1, now()->addDays(2)->startOfDay()->addHours(6)->addMinutes($round), 1);
    $jobs = [];
    for ($i = 1; $i <= $processes; $i++) {
        $jobs[] = ['ops' => [['type' => 'create', 'out' => $trip->id, 'ret' => null, 'pax' => 1, 'key' => "single-{$trip->id}-{$i}"]]];
    }
    $t = tally(spawnAll($jobs, $staffId));
    $seats = (int) Trip::find($trip->id)->seats_taken;
    $legs = BookingLeg::where('trip_id', $trip->id)->count();
    $pass = $t['ok'] === 1 && $t['reject'] === $processes - 1 && $t['error'] === 0 && ! $t['crashed'] && $seats === 1 && $legs === 1;
    $note = $pass ? '' : "สำเร็จ={$t['ok']} ปฏิเสธ={$t['reject']} error={$t['error']} seats_taken={$seats}/1 legs={$legs} ".implode(' | ', array_merge($t['errors'], $t['crashed']));

    return [$pass, $t, $note];
}

// --------------------------------- roundtrip --------------------------------
function runRoundTrip(int $round, int $processes, Route $r1, Route $r2, int $staffId): array
{
    $day = now()->addDays(3)->startOfDay()->addMinutes($round);
    $returnScarce = $round % 2 === 1; // รอบคี่: ขากลับเหลือ 1 ที่ / รอบคู่: ขาไปเหลือ 1 ที่
    $out = makeTrip($r1, $day->copy()->setTime(8, 0), $returnScarce ? $processes + 5 : 1);
    $ret = makeTrip($r2, $day->copy()->addDay()->setTime(8, 0), $returnScarce ? 1 : $processes + 5);
    $before = Booking::count();

    $jobs = [];
    for ($i = 1; $i <= $processes; $i++) {
        $jobs[] = ['ops' => [['type' => 'create', 'out' => $out->id, 'ret' => $ret->id, 'pax' => 1, 'key' => "rt-{$out->id}-{$i}"]]];
    }
    $t = tally(spawnAll($jobs, $staffId));

    $outSeats = (int) Trip::find($out->id)->seats_taken;
    $retSeats = (int) Trip::find($ret->id)->seats_taken;
    $newBookings = Booking::count() - $before;
    $legs = BookingLeg::whereIn('trip_id', [$out->id, $ret->id])->count();
    // ขาที่ไม่เต็มต้องมี 1 พอดี = ไม่มีที่นั่งค้างจากคำขอที่แพ้อีกขาหนึ่ง
    $pass = $t['ok'] === 1 && $t['reject'] === $processes - 1 && $t['error'] === 0 && ! $t['crashed']
        && $outSeats === 1 && $retSeats === 1 && $newBookings === 1 && $legs === 2;
    $note = $pass ? '' : sprintf('(%s) สำเร็จ=%d ปฏิเสธ=%d error=%d ขาไป seats=%d ขากลับ seats=%d จองใหม่=%d legs=%d %s',
        $returnScarce ? 'ขากลับเหลือ 1' : 'ขาไปเหลือ 1', $t['ok'], $t['reject'], $t['error'], $outSeats, $retSeats, $newBookings, $legs,
        implode(' | ', array_merge($t['errors'], $t['crashed'])));

    return [$pass, $t, $note];
}

// ----------------------------------- mixed ----------------------------------
function runMixed(int $round, int $processes, Route $r1, Route $r2, int $staffId): array
{
    mt_srand($round * 7919);
    $day = now()->addDays(4)->startOfDay()->addMinutes($round * 3);
    // สร้างรอบสลับ role (O=ขาไป B=ขากลับ) เพื่อให้ id ของขาไป/ขากลับสลับมากน้อยต่างกันในแต่ละคู่ที่สุ่ม
    $outTrips = [];
    $retTrips = [];
    foreach (['B', 'O', 'O', 'B', 'B', 'O', 'O', 'B'] as $k => $kind) {
        if ($kind === 'O') {
            $outTrips[] = makeTrip($r1, $day->copy()->setTime(8, $k), 4);
        } else {
            $retTrips[] = makeTrip($r2, $day->copy()->addDay()->setTime(8, $k), 4);
        }
    }
    $tripIds = array_merge(array_map(fn ($t) => $t->id, $outTrips), array_map(fn ($t) => $t->id, $retTrips));

    // จองเตรียมไว้ 6 รายการ ครึ่งหนึ่งตั้งให้หมดเวลาแล้ว (ให้ expire/ยกเลิกชนกับการจองใหม่)
    $svc = app(BookingService::class);
    $seeded = [];
    for ($i = 0; $i < 6; $i++) { // กระจายเท่า ๆ กัน (ไม่สุ่ม) ให้การเตรียมข้อมูลไม่เต็มเอง ความสุ่มอยู่ที่ขั้นแข่งขันด้านล่าง
        $o = $outTrips[$i % count($outTrips)];
        $r = $retTrips[($i + 1) % count($retTrips)];
        $seeded[] = $svc->create(['outbound_trip_id' => $o->id, 'return_trip_id' => $r->id, 'passengers' => 1, 'contact_name' => 'seed', 'contact_phone' => '0811111111', 'contact_email' => 'seed@example.test', 'idempotency_key' => "seed-{$round}-{$i}"])->id;
    }
    Booking::whereIn('id', array_slice($seeded, 0, 3))->update(['expires_at' => now()->subMinute()]);

    $jobs = [];
    for ($p = 1; $p <= $processes; $p++) {
        $ops = [];
        for ($k = 1; $k <= 3; $k++) {
            $roll = mt_rand(1, 100);
            if ($roll <= 60) {
                $ops[] = ['type' => 'create', 'out' => $outTrips[array_rand($outTrips)]->id, 'ret' => $retTrips[array_rand($retTrips)]->id, 'pax' => mt_rand(1, 2), 'key' => "mix-{$round}-{$p}-{$k}"];
            } elseif ($roll <= 85) {
                $ops[] = ['type' => 'cancel', 'id' => $seeded[array_rand($seeded)]];
            } else {
                $ops[] = ['type' => 'expire'];
            }
        }
        $jobs[] = ['ops' => $ops];
    }
    $t = tally(spawnAll($jobs, $staffId));

    // ตรวจความถูกต้องหลังจบ: ที่นั่งที่กันไว้ของทุกรอบเรือ = ผลรวมที่นั่งของการจองที่ยังไม่ถูกคืน และอยู่ในโควตา
    $problems = [];
    foreach (DB::table('trips')->whereIn('id', $tripIds)->get() as $trip) {
        $held = (int) DB::table('booking_legs')->join('bookings', 'bookings.id', '=', 'booking_legs.booking_id')
            ->where('booking_legs.trip_id', $trip->id)->whereNull('bookings.seats_released_at')->sum('booking_legs.passengers');
        if ($held !== (int) $trip->seats_taken) {
            $problems[] = "trip#{$trip->id} seats_taken={$trip->seats_taken} แต่การจองที่ยังไม่คืนรวม={$held}";
        }
        if ($trip->seats_taken > $trip->capacity) {
            $problems[] = "trip#{$trip->id} จองเกินโควตา {$trip->seats_taken}/{$trip->capacity}";
        }
    }
    $inconsistent = Booking::where(fn ($q) => $q->whereNull('seats_released_at')->whereIn('booking_status', ['cancelled', 'expired']))
        ->orWhere(fn ($q) => $q->whereNotNull('seats_released_at')->whereIn('booking_status', ['pending', 'confirmed']))->count();
    if ($inconsistent > 0) {
        $problems[] = "สถานะการจองไม่สอดคล้องกับการคืนที่นั่ง {$inconsistent} รายการ";
    }

    $pass = $t['error'] === 0 && ! $t['crashed'] && ! $problems;
    $note = $pass ? '' : sprintf('error=%d %s %s', $t['error'], implode(' | ', array_merge($t['errors'], $t['crashed'])), implode(' ; ', $problems));

    return [$pass, $t, $note];
}

// ---------------------------------- วนรอบ -----------------------------------
$scenarios = $scenario === 'all' ? ['single', 'roundtrip', 'mixed'] : [$scenario];
$titles = ['single' => 'เที่ยวเดียวแย่งที่นั่งสุดท้าย', 'roundtrip' => '(ก) ไป–กลับพร้อมกัน ที่นั่งสุดท้ายของอีกขา', 'mixed' => '(ข) งานผสม ล็อกสลับลำดับ จอง/ยกเลิก/คืนที่นั่ง'];
$started = microtime(true);

foreach ($scenarios as $name) {
    echo "\n== {$titles[$name]} ({$rounds} รอบ × {$processes} โปรเซส) ==\n";
    $agg = ['pass' => 0, 'fail' => 0, 'ok' => 0, 'reject' => 0, 'error' => 0, 'retries' => 0, 'deadlock' => 0];
    for ($round = 1; $round <= $rounds; $round++) {
        [$pass, $t, $note] = match ($name) {
            'single' => runSingle($round, $processes, $r1, $staff->id),
            'roundtrip' => runRoundTrip($round, $processes, $r1, $r2, $staff->id),
            'mixed' => runMixed($round, $processes, $r1, $r2, $staff->id),
        };
        $agg[$pass ? 'pass' : 'fail']++;
        foreach (['ok', 'reject', 'error', 'retries', 'deadlock'] as $k) {
            $agg[$k] += $t[$k];
        }
        if (! $pass) {
            echo "  รอบ {$round}: ไม่ผ่าน — {$note}\n";
        } elseif ($t['retries'] > 0 || $round % 5 === 0 || $round === $rounds) {
            printf("  รอบ %2d: ผ่าน (สำเร็จ=%d ปฏิเสธ=%d error=%d retry=%d)\n", $round, $t['ok'], $t['reject'], $t['error'], $t['retries']);
        }
    }
    $summary[$name] = $agg;
    $allPassed = $allPassed && $agg['fail'] === 0;
}

echo "\n==================== สรุป ====================\n";
foreach ($summary as $name => $a) {
    printf("%-52s ผ่าน %d/%d รอบ | คำขอ: สำเร็จ=%d ปฏิเสธ=%d error=%d | retry ที่เกิดขึ้น=%d | deadlock/1020 ที่หลุดถึงผู้ใช้=%d\n",
        $titles[$name], $a['pass'], $a['pass'] + $a['fail'], $a['ok'], $a['reject'], $a['error'], $a['retries'], $a['deadlock']);
}
printf("เวลารวม %.0f วินาที\n", microtime(true) - $started);
echo $allPassed ? "ผลรวม: ผ่านทุกรอบ\n" : "ผลรวม: มีรอบที่ไม่ผ่าน\n";
exit($allPassed ? 0 : 1);
