<?php

/**
 * ทดสอบแย่งที่นั่งสุดท้ายแบบหลายโปรเซสพร้อมกัน (ต้องใช้ MariaDB/MySQL จริง — SQLite ในหน่วยความจำใช้ข้ามโปรเซสไม่ได้)
 *
 * แต่ละรอบ: สร้างรอบเรือที่เหลือ 1 ที่นั่ง แล้วสั่ง N โปรเซสจองพร้อมกัน (รอเวลาเริ่มเดียวกัน)
 * ต้องได้ "จองสำเร็จ 1 รายการ ที่เหลือถูกปฏิเสธ" และ seats_taken = 1, มีการจองในรอบนั้น 1 รายการ
 *
 * วิธีรัน (ตัวอย่าง PowerShell — ดู README):
 *   $env:DB_CONNECTION='mariadb'; $env:DB_DATABASE='ferry_booking_test'; $env:DB_USERNAME='...'; $env:DB_PASSWORD='...'
 *   php scripts/race-last-seat.php --processes=8 --rounds=3
 *
 * ⚠ สคริปต์นี้ล้างตารางทั้งฐาน (migrate:fresh) จึงปฏิเสธการรันถ้าชื่อฐานข้อมูลไม่ลงท้ายด้วย "_test"
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\BookingLeg;
use App\Models\Island;
use App\Models\Port;
use App\Models\Route;
use App\Models\Trip;
use App\Services\BookingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$opts = getopt('', ['child:', 'n:', 'start:', 'processes::', 'rounds::']);

// ---------- โหมดลูก: จอง 1 ครั้งตามเวลาเริ่มที่กำหนด แล้วพิมพ์ผลบรรทัดเดียว ----------
if (isset($opts['child'])) {
    $start = (float) $opts['start'];
    while (microtime(true) < $start) {
        usleep(200);
    }
    try {
        $booking = app(BookingService::class)->create([
            'outbound_trip_id' => (int) $opts['child'], 'passengers' => 1, 'contact_name' => 'race-'.$opts['n'],
            'contact_phone' => '0811111111', 'contact_email' => 'race-'.$opts['n'].'@example.test',
            'idempotency_key' => 'race-'.$opts['child'].'-'.$opts['n'],
        ]);
        echo 'OK '.$booking->reference.PHP_EOL;
    } catch (App\Exceptions\BookingException) {
        echo 'FULL'.PHP_EOL;
    } catch (Throwable $e) {
        echo 'ERROR '.get_class($e).': '.str_replace(["\r", "\n"], ' ', $e->getMessage()).PHP_EOL;
    }
    exit(0);
}

// ---------- โหมดหลัก ----------
$processes = max(2, (int) ($opts['processes'] ?? 8));
$rounds = max(1, (int) ($opts['rounds'] ?? 3));
$driver = DB::connection()->getDriverName();
$database = (string) DB::connection()->getDatabaseName();

if (app()->environment('production') || ! in_array($driver, ['mariadb', 'mysql'], true) || ! str_ends_with($database, '_test')) {
    fwrite(STDERR, "ปฏิเสธการรัน: ต้องใช้ MariaDB/MySQL ที่ชื่อฐานข้อมูลลงท้าย _test (ตอนนี้: {$driver} / {$database})\n"
        ."ตั้งค่าด้วย DB_CONNECTION, DB_DATABASE (เช่น ferry_booking_test), DB_USERNAME, DB_PASSWORD แล้วรันใหม่ — ดูหัวไฟล์\n");
    exit(2);
}

echo "ฐานข้อมูลทดสอบ: {$driver}/{$database} · โปรเซสต่อรอบ: {$processes} · จำนวนรอบ: {$rounds}\n";
Artisan::call('migrate:fresh', ['--force' => true]);

$island = Island::create(['name' => 'เกาะทดสอบแข่งขัน', 'slug' => 'race']);
$origin = Port::create(['name' => 'ท่า A']);
$dest = Port::create(['name' => 'ท่า B', 'island_id' => $island->id]);
$route = Route::create(['origin_port_id' => $origin->id, 'destination_port_id' => $dest->id, 'island_id' => $island->id, 'duration_minutes' => 60]);

$allPassed = true;
for ($round = 1; $round <= $rounds; $round++) {
    $departs = now()->addDays(2)->startOfDay()->addHours(7 + $round);
    $trip = Trip::create(['route_id' => $route->id, 'departs_at' => $departs, 'arrives_at' => $departs->copy()->addHour(), 'price' => 450, 'capacity' => 1]);

    $start = microtime(true) + 2.5; // เวลาเริ่มร่วม ให้ทุกโปรเซสบูตเสร็จก่อนแล้วยิงพร้อมกัน
    $procs = [];
    for ($i = 1; $i <= $processes; $i++) {
        $cmd = [PHP_BINARY, __FILE__, '--child='.$trip->id, '--n='.$i, '--start='.sprintf('%.6F', $start)];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[] = [$proc, $pipes];
    }

    $results = [];
    foreach ($procs as [$proc, $pipes]) {
        $results[] = trim((string) stream_get_contents($pipes[1])).trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
    }

    $ok = count(array_filter($results, fn ($r) => str_starts_with($r, 'OK ')));
    $full = count(array_filter($results, fn ($r) => $r === 'FULL'));
    $errors = array_values(array_filter($results, fn ($r) => ! str_starts_with($r, 'OK ') && $r !== 'FULL'));
    $seats = (int) Trip::find($trip->id)->seats_taken;
    $legs = BookingLeg::where('trip_id', $trip->id)->count();
    $bookings = Booking::count();

    $pass = $ok === 1 && $full === $processes - 1 && ! $errors && $seats === 1 && $legs === 1;
    $allPassed = $allPassed && $pass;
    printf("รอบ %d: จองสำเร็จ=%d ถูกปฏิเสธ=%d ผิดพลาด=%d | seats_taken=%d/1 legs=%d bookings(รวม)=%d => %s\n",
        $round, $ok, $full, count($errors), $seats, $legs, $bookings, $pass ? 'ผ่าน' : 'ไม่ผ่าน');
    foreach ($errors as $e) {
        echo "   {$e}\n";
    }
}

echo $allPassed ? "สรุป: ผ่านทุกรอบ — ที่นั่งสุดท้ายรับได้เพียง 1 รายการ\n" : "สรุป: ไม่ผ่าน\n";
exit($allPassed ? 0 : 1);
