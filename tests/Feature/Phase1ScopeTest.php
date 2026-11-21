<?php

use App\Livewire\Admin\BookingDetail;
use App\Livewire\BookingStatus;
use App\Livewire\Home;
use App\Models\Booking;
use App\Models\Port;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use App\Services\BookingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// ตรวจสโคปเฟส 1 ส่วนที่เหลือ: สลับเกาะ, ยอดฝั่งเซิร์ฟเวอร์, ยอดไม่ตรง, เลยเวลาโอน, อีเมล/LINE, ตัวจับเวลา, สถานะแยกกัน

beforeEach(function () {
    $this->withoutVite();
    $this->seed(DatabaseSeeder::class);
    $this->staff = User::where('email', 'staff@demo.test')->first();
});

function scopeTrip(): Trip
{
    $trat = Port::where('name', 'ท่าเรือตราด')->first();
    $kood = Port::where('name', 'ท่าเรือเกาะกูด')->first();
    $route = Route::where('origin_port_id', $trat->id)->where('destination_port_id', $kood->id)->first();

    return Trip::where('route_id', $route->id)->where('departs_at', '>', now()->addDay())->orderBy('departs_at')->first();
}

function scopeBooking(Trip $trip, array $o = []): Booking
{
    return app(BookingService::class)->create(array_merge([
        'outbound_trip_id' => $trip->id, 'passengers' => 2, 'contact_name' => 'ทดสอบสโคป', 'contact_phone' => '0811111111',
        'contact_email' => 'scope@example.test', 'idempotency_key' => bin2hex(random_bytes(8)),
    ], $o));
}

function scopeSlip(Booking $b, ?int $amount = null): void
{
    app(BookingService::class)->submitPayment($b, $amount ?? $b->total_amount, now(), UploadedFile::fake()->image('slip.jpg'));
}

// ---------- 1) สลับเกาะ: ภาพปก/ชื่อ/ปลายทาง/รอบเรือเปลี่ยนพร้อมกัน เก็บวันที่-จำนวนคน-ต้นทาง ----------
it('สลับเกาะ: ภาพปก ชื่อเกาะ ปลายทาง และตารางเรือเปลี่ยนใน render เดียวกัน', function () {
    // ภาพปกหลักระบุด้วย data-hero-cover (ไม่ผูกกับชื่อไฟล์ภาพ เปลี่ยนภาพปกได้โดยเทสต์ไม่พัง)
    $hero = fn ($c) => preg_match('/<img[^>]*data-hero-cover[^>]*src="([^"]+)"/', $c->html(), $m) ? $m[1] : null;
    $coverOf = fn (string $slug) => \App\Models\Island::where('slug', $slug)->first()->cover_url;

    $c = Livewire::test(Home::class)->set('departDate', now()->addDays(4)->toDateString())->set('passengers', 3)
        ->assertSee('ไปเกาะกูด')->assertSee('ท่าเรือเกาะกูด')->assertSee('450');
    expect($hero($c))->toBe($coverOf('koh-kood'));

    $c->call('selectIsland', 'koh-mak')
        ->assertSee('ไปเกาะหมาก')->assertSee('ท่าเรือเกาะหมาก')->assertDontSee('ท่าเรือเกาะกูด')->assertSee('400')
        ->assertSet('departDate', now()->addDays(4)->toDateString())->assertSet('passengers', 3);
    expect($hero($c))->toBe($coverOf('koh-mak'))->not->toBe($coverOf('koh-kood'));
});

// ---------- 2) ยอดคำนวณฝั่งเซิร์ฟเวอร์ ไม่เชื่อค่าจากลูกค้า ----------
it('ยอดรวมคำนวณฝั่งเซิร์ฟเวอร์เสมอ ไม่เชื่อยอด/ราคาที่ลูกค้าแนบมา และเก็บราคารายขา', function () {
    $trip = scopeTrip();
    $b = scopeBooking($trip, ['total_amount' => 1, 'price' => 1, 'unit_price' => 1, 'amount' => 1]); // พยายามยัดค่าเอง

    expect($b->total_amount)->toBe(2 * $trip->price)->and($b->legs->first()->unit_price)->toBe($trip->price)->and($b->legs->first()->amount)->toBe(2 * $trip->price);
    $trip->update(['price' => 999]); // แก้ราคารอบภายหลัง ไม่กระทบรายการเดิม
    expect($b->fresh()->total_amount)->toBe(2 * 450)->and($b->fresh()->legs->first()->unit_price)->toBe(450);
});

// ---------- 3) ยอดไม่ตรง: ผ่านหน้าจอทั้งสองฝั่ง ----------
it('ยอดไม่ตรง: เจ้าหน้าที่บันทึกเหตุผล ลูกค้าเห็นเหตุผลและแนบสลิปใหม่ แล้วเจ้าหน้าที่ยืนยันได้ (ผ่านหน้าจอ)', function () {
    Storage::fake('local');
    $b = scopeBooking(scopeTrip());
    scopeSlip($b, 100); // ลูกค้าแจ้งยอด 100 (ยอดจริง 900)
    $this->actingAs($this->staff);

    $d = Livewire::test(BookingDetail::class, ['booking' => $b])->assertSee('ไม่ตรงกับยอดจอง');
    $d->call('requestEvidence')->assertSee('กรุณาระบุเหตุผล'); // ไม่ใส่เหตุผลต้องไม่ผ่าน
    $d->set('evidenceReason', 'โอนมา 100 บาท ยอดจริง 900 บาท')->call('requestEvidence')->assertSee('ขอหลักฐานใหม่จากลูกค้าแล้ว');
    expect($b->fresh()->payment_status)->toBe('rejected')->and($b->fresh()->booking_status)->toBe('pending');

    $c = Livewire::test(BookingStatus::class, ['token' => $b->access_token])
        ->assertSee('ขอหลักฐานใหม่')->assertSee('โอนมา 100 บาท ยอดจริง 900 บาท');
    $c->set('amount', 900)->set('transferredAt', now()->subMinute()->format('Y-m-d\TH:i'))
        ->set('slip', UploadedFile::fake()->image('slip2.jpg'))->call('submitPayment')->assertHasNoErrors()->assertSee('รอตรวจยอด');

    Livewire::test(BookingDetail::class, ['booking' => $b])->assertSee('(ตรงกับยอดจอง)')->call('confirm');
    expect($b->fresh()->booking_status)->toBe('confirmed')->and($b->payments()->count())->toBe(2);
});

// ---------- 4) ตรวจสลิปหลังเลยเวลาโอน ถ้าส่งทันเวลา ----------
it('สลิปที่ส่งทันเวลา: เลยกำหนดโอนแล้วเจ้าหน้าที่ยังตรวจและยืนยันได้ผ่านหน้าจอ (ไม่ถูกหมดเวลาเอง)', function () {
    Storage::fake('local');
    $b = scopeBooking(scopeTrip());
    scopeSlip($b);
    $b->update(['expires_at' => now()->subHours(3)]); // เลยกำหนดโอนไปแล้ว
    expect(app(BookingService::class)->expireDue())->toBe(0);

    $this->actingAs($this->staff);
    Livewire::test(BookingDetail::class, ['booking' => $b])->assertSee('ยืนยันการจอง')->call('confirm')->assertSee('ยืนยันการจองแล้ว');
    expect($b->fresh()->booking_status)->toBe('confirmed');
});

// ---------- 5) อีเมลลง log และปุ่มคัดลอกข้อความ LINE ----------
it('อีเมลยืนยันถูกบันทึกลง log เมื่อใช้ MAIL_MAILER=log พร้อมเลขจอง', function () {
    Storage::fake('local');
    $path = tempnam(sys_get_temp_dir(), 'ferrymail');
    config(['logging.channels.ferry_mail_test' => ['driver' => 'single', 'path' => $path], 'mail.default' => 'log', 'mail.mailers.log.channel' => 'ferry_mail_test']);
    app('mail.manager')->forgetMailers();

    $b = scopeBooking(scopeTrip());
    scopeSlip($b);
    app(BookingService::class)->confirm($b->fresh(), $this->staff);

    $raw = file_get_contents($path);
    @unlink($path);
    $decoded = quoted_printable_decode($raw);
    if (preg_match('/Content-Transfer-Encoding: base64\s+(.+?)(?:\r?\n--|\z)/s', $raw, $m)) {
        $decoded .= base64_decode(preg_replace('/\s+/', '', $m[1]));
    }
    expect($decoded)->toContain($b->reference)->toContain('scope@example.test');
});

it('หน้ารายละเอียดมีปุ่มคัดลอกข้อความ LINE เฉพาะรายการที่ยืนยันแล้ว และข้อความมีเลขจอง', function () {
    Storage::fake('local');
    $this->actingAs($this->staff);
    $b = scopeBooking(scopeTrip());

    Livewire::test(BookingDetail::class, ['booking' => $b])->assertDontSee('คัดลอกข้อความ'); // ยังไม่ยืนยัน
    scopeSlip($b);
    app(BookingService::class)->confirm($b->fresh(), $this->staff);
    Livewire::test(BookingDetail::class, ['booking' => $b])
        ->assertSee('คัดลอกข้อความ')->assertSee('เลขจอง: '.$b->reference)->assertSee('ท่าเรือตราด')
        ->assertSeeHtml('navigator.clipboard')->assertSeeHtml("execCommand('copy')"); // มีทางสำรองเมื่อไม่ใช่ HTTPS
});

// ---------- 6) ตัวจับเวลาหมดอายุ ----------
it('คำสั่ง bookings:expire มีอยู่ คืนที่นั่งรายการหมดเวลา และถูกตั้งเวลาทุกนาที', function () {
    $b = scopeBooking($trip = scopeTrip());
    $b->update(['expires_at' => now()->subMinute()]);

    $this->artisan('bookings:expire')->assertExitCode(0)->expectsOutputToContain('คืนที่นั่งจากรายการหมดเวลา 1 รายการ');
    expect($b->fresh()->booking_status)->toBe('expired')->and($trip->fresh()->seats_taken)->toBe(0);

    $this->artisan('schedule:list')->assertExitCode(0)->expectsOutputToContain('bookings:expire');
    $event = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'bookings:expire'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe('* * * * *');
});

// ---------- 7) สถานะการจองกับการชำระแยกกัน ----------
it('สถานะการจองและสถานะการชำระเงินแยกกัน แสดงแยกทั้งหน้าลูกค้าและหลังบ้าน', function () {
    Storage::fake('local');
    $this->actingAs($this->staff);
    $b = scopeBooking(scopeTrip());

    $show = fn () => [Livewire::test(BookingStatus::class, ['token' => $b->access_token]), Livewire::test(BookingDetail::class, ['booking' => $b])];
    $assertBoth = function (string $booking, string $payment) use ($show) {
        foreach ($show() as $c) {
            $c->assertSee('สถานะการจอง: '.$booking)->assertSee('การชำระเงิน: '.$payment);
        }
    };

    $assertBoth('รอดำเนินการ', 'ยังไม่แจ้งโอน');                       // จองแล้ว ยังไม่โอน
    scopeSlip($b);
    $assertBoth('รอดำเนินการ', 'รอตรวจยอด');                           // แจ้งโอนแล้ว การจองยังไม่ยืนยัน
    app(BookingService::class)->requestNewEvidence($b->fresh(), $this->staff, 'ยอดไม่ครบ');
    $assertBoth('รอดำเนินการ', 'ยอดไม่ตรง/ขอหลักฐานใหม่');            // การชำระเปลี่ยน แต่การจองยังรอดำเนินการ
    scopeSlip($b->fresh());
    app(BookingService::class)->confirm($b->fresh(), $this->staff);
    $assertBoth('ยืนยันแล้ว', 'ตรวจยอดแล้ว');                          // ทั้งคู่เปลี่ยนพร้อมกันเมื่อยืนยัน

    Livewire::test(App\Livewire\Admin\Bookings::class)->assertSee('สถานะการจอง')->assertSee('การชำระเงิน'); // คอลัมน์แยกในรายการ
});
