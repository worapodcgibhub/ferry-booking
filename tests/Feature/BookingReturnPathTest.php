<?php

use App\Livewire\BookingStatus;
use App\Mail\BookingConfirmedMail;
use App\Mail\BookingReceivedMail;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use App\Services\BookingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

// เส้นทางลูกค้าที่จองแล้วกลับมาแจ้งโอนทีหลัง: ข้อความหน้าสถานะ + อีเมลตอนจอง

beforeEach(function () {
    $this->withoutVite();
    $this->seed(DatabaseSeeder::class);
});

function returnPathBooking(): Booking
{
    $trip = Trip::where('departs_at', '>', now()->addDay())->orderBy('departs_at')->firstOrFail();

    return app(BookingService::class)->create([
        'outbound_trip_id' => $trip->id, 'passengers' => 2, 'contact_name' => 'สมหญิง',
        'contact_phone' => '0812345678', 'contact_email' => 'rp@example.test',
        'idempotency_key' => bin2hex(random_bytes(8)),
    ]);
}

it('หน้าสถานะที่รอแจ้งโอนบอกให้กลับมาที่หน้านี้ภายในเวลาหมดอายุ มีปุ่มคัดลอกลิงก์และลิงก์ตรวจสอบการจอง', function () {
    Mail::fake();
    $b = returnPathBooking();
    $deadline = thai_datetime($b->expires_at);

    Livewire::test(BookingStatus::class, ['token' => $b->access_token])
        ->assertSee('ถ้าโอนทีหลัง ให้กลับมาที่หน้านี้ภายใน '.$deadline.' น.')
        ->assertSee('คัดลอกลิงก์หน้านี้')
        ->assertSee(route('booking.show', $b->access_token), false)
        ->assertSeeHtml('href="'.route('booking.lookup').'"');
});

it('หน้าหมดเวลา: บอกเวลาที่หมด ลิงก์แจ้งโอนไม่ได้อีก และมีปุ่มจองใหม่', function () {
    Mail::fake();
    $b = returnPathBooking();
    $this->travel(61)->minutes();
    $deadline = thai_datetime($b->expires_at);

    Livewire::test(BookingStatus::class, ['token' => $b->access_token])
        ->assertSee('หมดเวลาแจ้งโอน')
        ->assertSee('เมื่อ '.$deadline.' น.')
        ->assertSee('จองใหม่')
        ->assertDontSee('แจ้งโอนและแนบสลิป')
        ->assertDontSee('ถ้าโอนทีหลัง');
    expect($b->fresh()->booking_status)->toBe('expired');
});

it('สร้างการจองแล้วส่ง BookingReceivedMail พร้อมเลขอ้างอิง ลิงก์หน้าสถานะ ยอดเงิน และเวลาหมดอายุ', function () {
    Mail::fake();
    $b = returnPathBooking();

    Mail::assertSent(BookingReceivedMail::class, 1);
    Mail::assertSent(BookingReceivedMail::class, function ($m) use ($b) {
        $html = $m->render();

        return $m->hasTo('rp@example.test')
            && str_contains($html, $b->reference)
            && str_contains($html, route('booking.show', $b->access_token))
            && str_contains($html, number_format($b->total_amount).' บาท')
            && str_contains($html, thai_datetime($b->expires_at))
            && str_contains($html, 'ถ้าโอนทีหลัง ให้กลับมาที่ลิงก์นี้ภายใน');
    });
});

it('ส่งซ้ำด้วย idempotency key เดิม: ไม่ส่งอีเมลซ้ำ', function () {
    Mail::fake();
    $trip = Trip::where('departs_at', '>', now()->addDay())->orderBy('departs_at')->firstOrFail();
    $d = ['outbound_trip_id' => $trip->id, 'passengers' => 1, 'contact_name' => 'ก', 'contact_phone' => '0811111111',
        'contact_email' => 'dup@example.test', 'idempotency_key' => 'same-key-123'];
    app(BookingService::class)->create($d);
    app(BookingService::class)->create($d);

    expect(Booking::count())->toBe(1);
    Mail::assertSent(BookingReceivedMail::class, 1);
});

it('ส่งอีเมลไม่สำเร็จ: การจองยังสำเร็จและที่นั่งถูกกัน', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));
    $trip = Trip::where('departs_at', '>', now()->addDay())->orderBy('departs_at')->firstOrFail();
    $before = $trip->seats_taken;

    $b = returnPathBooking();

    expect(Booking::find($b->id))->not->toBeNull()
        ->and($b->booking_status)->toBe('pending')
        ->and(Trip::find($trip->id)->seats_taken)->toBe($before + 2);
});

it('BookingConfirmedMail มีลิงก์หน้าสถานะของการจอง', function () {
    Mail::fake();
    $b = returnPathBooking();
    $staff = User::where('email', 'staff@demo.test')->first();
    $this->actingAs($staff);
    $b->update(['payment_status' => 'submitted']);
    app(BookingService::class)->confirm($b, $staff);

    Mail::assertSent(BookingConfirmedMail::class, fn ($m) => str_contains($m->render(), route('booking.show', $b->access_token)));
});
