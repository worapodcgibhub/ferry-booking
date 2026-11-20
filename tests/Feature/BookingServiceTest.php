<?php

use App\Exceptions\BookingException;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Island;
use App\Models\Port;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function ferryFixture(int $outCap = 10, int $retCap = 10): array
{
    $island = Island::create(['name' => 'เกาะทดสอบ', 'slug' => 'test']);
    $a = Port::create(['name' => 'ท่า A']);
    $b = Port::create(['name' => 'ท่า B', 'island_id' => $island->id]);
    $go = Route::create(['origin_port_id' => $a->id, 'destination_port_id' => $b->id, 'island_id' => $island->id, 'duration_minutes' => 60]);
    $back = Route::create(['origin_port_id' => $b->id, 'destination_port_id' => $a->id, 'island_id' => $island->id, 'duration_minutes' => 60]);
    $day = now()->addDays(3)->startOfDay();
    $out = Trip::create(['route_id' => $go->id, 'departs_at' => $day->copy()->setTime(8, 0), 'arrives_at' => $day->copy()->setTime(9, 0), 'price' => 450, 'capacity' => $outCap]);
    $ret = Trip::create(['route_id' => $back->id, 'departs_at' => $day->copy()->setTime(15, 0), 'arrives_at' => $day->copy()->setTime(16, 0), 'price' => 500, 'capacity' => $retCap]);

    return [$out, $ret];
}

function bookingData(array $o = []): array
{
    return array_merge([
        'passengers' => 2, 'contact_name' => 'ลูกค้าทดสอบ', 'contact_phone' => '0810000000',
        'contact_email' => 'cust@example.test', 'idempotency_key' => bin2hex(random_bytes(8)),
    ], $o);
}

function svc(): BookingService
{
    return app(BookingService::class);
}

it('จองเที่ยวเดียว คำนวณยอดฝั่งเซิร์ฟเวอร์และกันที่นั่ง', function () {
    [$out] = ferryFixture();
    $b = svc()->create(bookingData(['outbound_trip_id' => $out->id, 'passengers' => 3]));

    expect($b->total_amount)->toBe(1350)->and($b->trip_type)->toBe('oneway')
        ->and($b->booking_status)->toBe('pending')->and($b->payment_status)->toBe('unpaid')
        ->and($b->legs)->toHaveCount(1)->and($out->fresh()->seats_taken)->toBe(3);
});

it('จองไป–กลับ เก็บราคาแต่ละขา เลขจองเดียว และกันที่ครบทั้งสองขา', function () {
    [$out, $ret] = ferryFixture();
    $b = svc()->create(bookingData(['outbound_trip_id' => $out->id, 'return_trip_id' => $ret->id, 'passengers' => 2]));

    expect($b->total_amount)->toBe(2 * 450 + 2 * 500)
        ->and($b->legs->pluck('unit_price')->all())->toBe([450, 500])
        ->and($out->fresh()->seats_taken)->toBe(2)->and($ret->fresh()->seats_taken)->toBe(2);

    // เปลี่ยนราคาภายหลังไม่กระทบยอดที่จองไว้
    $out->update(['price' => 999]);
    expect($b->fresh()->legs->first()->unit_price)->toBe(450);
});

it('ขากลับเต็ม: ไม่เกิดการจองและไม่มีขาไปค้าง', function () {
    [$out, $ret] = ferryFixture(10, 1);

    expect(fn () => svc()->create(bookingData(['outbound_trip_id' => $out->id, 'return_trip_id' => $ret->id, 'passengers' => 2])))
        ->toThrow(BookingException::class);

    expect(Booking::count())->toBe(0)->and($out->fresh()->seats_taken)->toBe(0)->and($ret->fresh()->seats_taken)->toBe(0);
});

it('ที่นั่งสุดท้ายรับได้เพียงหนึ่งรายการ', function () {
    [$out] = ferryFixture(1);
    svc()->create(bookingData(['outbound_trip_id' => $out->id, 'passengers' => 1]));

    expect(fn () => svc()->create(bookingData(['outbound_trip_id' => $out->id, 'passengers' => 1])))
        ->toThrow(BookingException::class);
    expect(Booking::count())->toBe(1)->and($out->fresh()->seats_taken)->toBe(1);
});

it('ส่งซ้ำด้วยคีย์เดิมได้เลขจองเดียว และไม่หักที่นั่งซ้ำ', function () {
    [$out] = ferryFixture();
    $data = bookingData(['outbound_trip_id' => $out->id, 'passengers' => 2]);
    $a = svc()->create($data);
    $b = svc()->create($data);

    expect($b->id)->toBe($a->id)->and(Booking::count())->toBe(1)->and($out->fresh()->seats_taken)->toBe(2);
});

it('ปฏิเสธขากลับที่ไม่ตรงเส้นทางหรือออกก่อนเรือขาไปถึง', function () {
    [$out, $ret] = ferryFixture();
    $ret->update(['departs_at' => $out->arrives_at->copy()->subMinutes(10)]);

    expect(fn () => svc()->create(bookingData(['outbound_trip_id' => $out->id, 'return_trip_id' => $ret->id])))
        ->toThrow(BookingException::class);
    expect(fn () => svc()->create(bookingData(['outbound_trip_id' => $out->id, 'return_trip_id' => $out->id])))
        ->toThrow(BookingException::class);
    expect($out->fresh()->seats_taken)->toBe(0);
});

it('ยกเลิกคืนที่นั่งครบทุกขาเพียงครั้งเดียว', function () {
    [$out, $ret] = ferryFixture();
    $b = svc()->create(bookingData(['outbound_trip_id' => $out->id, 'return_trip_id' => $ret->id, 'passengers' => 2]));
    $staff = User::factory()->create(['role' => 'staff']);

    svc()->cancel($b, $staff, 'ลูกค้าขอยกเลิก');
    expect($b->fresh()->booking_status)->toBe('cancelled')->and($out->fresh()->seats_taken)->toBe(0)->and($ret->fresh()->seats_taken)->toBe(0);

    // เรียกคืนซ้ำ (เช่น สถานะค้าง/หมดเวลาตามมา) ต้องไม่ติดลบหรือคืนเกิน
    expect(svc()->release($b->fresh(), 'expired'))->toBeFalse();
    expect($out->fresh()->seats_taken)->toBe(0);

    $other = svc()->create(bookingData(['outbound_trip_id' => $out->id, 'passengers' => 4]));
    svc()->release($b->fresh(), 'cancelled');
    expect($out->fresh()->seats_taken)->toBe(4)->and($other->fresh()->booking_status)->toBe('pending');
});

it('หมดเวลาโดยไม่แจ้งโอนจะคืนที่นั่ง แต่รายการที่แนบสลิปแล้วไม่ถูกคืน', function () {
    Storage::fake('local');
    [$out] = ferryFixture();
    $unpaid = svc()->create(bookingData(['outbound_trip_id' => $out->id, 'passengers' => 2]));
    $paid = svc()->create(bookingData(['outbound_trip_id' => $out->id, 'passengers' => 3]));
    svc()->submitPayment($paid, $paid->total_amount, now(), UploadedFile::fake()->image('slip.jpg'));

    Booking::query()->update(['expires_at' => now()->subMinute()]);
    expect(svc()->expireDue())->toBe(1);
    expect(svc()->expireDue())->toBe(0); // รันซ้ำไม่คืนซ้ำ

    expect($unpaid->fresh()->booking_status)->toBe('expired')->and($paid->fresh()->booking_status)->toBe('pending')
        ->and($out->fresh()->seats_taken)->toBe(3);
});

it('แจ้งโอนได้ครั้งเดียวต่อรอบ และแจ้งไม่ได้เมื่อหมดเวลา', function () {
    Storage::fake('local');
    [$out] = ferryFixture();
    $b = svc()->create(bookingData(['outbound_trip_id' => $out->id]));
    $slip = fn () => UploadedFile::fake()->image('slip.png');

    svc()->submitPayment($b, $b->total_amount, now(), $slip());
    expect($b->fresh()->payment_status)->toBe('submitted');
    expect(fn () => svc()->submitPayment($b->fresh(), $b->total_amount, now(), $slip()))->toThrow(BookingException::class);

    $late = svc()->create(bookingData(['outbound_trip_id' => $out->id]));
    $late->update(['expires_at' => now()->subSecond()]);
    expect(fn () => svc()->submitPayment($late->fresh(), $late->total_amount, now(), $slip()))->toThrow(BookingException::class);
});

it('ทีมงานยืนยันได้แม้เลยเวลาโอน ถ้าสลิปส่งทัน และส่งอีเมลพร้อมเลขจอง', function () {
    Storage::fake('local');
    Mail::fake();
    [$out, $ret] = ferryFixture();
    $b = svc()->create(bookingData(['outbound_trip_id' => $out->id, 'return_trip_id' => $ret->id]));
    svc()->submitPayment($b, $b->total_amount, now(), UploadedFile::fake()->image('s.jpg'));
    $b->update(['expires_at' => now()->subHour()]); // เลยเวลาโอนแล้ว
    $staff = User::factory()->create(['role' => 'staff']);

    svc()->confirm($b->fresh(), $staff);

    $b->refresh();
    expect($b->booking_status)->toBe('confirmed')->and($b->payment_status)->toBe('verified')->and($b->payments->first()->reviewed_by)->toBe($staff->id);
    Mail::assertSent(BookingConfirmedMail::class, fn ($m) => $m->hasTo('cust@example.test') && $m->booking->reference === $b->reference);
    // ยืนยันซ้ำไม่ได้ และยังไม่ถูกหมดเวลา
    expect(fn () => svc()->confirm($b->fresh(), $staff))->toThrow(BookingException::class);
    expect(svc()->expireDue())->toBe(0);
});

it('ยอดไม่ตรง: ต้องระบุเหตุผล เปิดให้ส่งหลักฐานใหม่ และยืนยันก่อนได้สลิปใหม่ไม่ได้', function () {
    Storage::fake('local');
    [$out] = ferryFixture();
    $b = svc()->create(bookingData(['outbound_trip_id' => $out->id]));
    svc()->submitPayment($b, 100, now(), UploadedFile::fake()->image('s.jpg'));
    $staff = User::factory()->create(['role' => 'staff']);

    expect(fn () => svc()->requestNewEvidence($b->fresh(), $staff, '  '))->toThrow(BookingException::class);
    svc()->requestNewEvidence($b->fresh(), $staff, 'ยอดโอนไม่ครบ');
    $b->refresh();
    expect($b->payment_status)->toBe('rejected')->and($b->payments->first()->review_note)->toBe('ยอดโอนไม่ครบ')
        ->and($b->isOpenForPayment())->toBeTrue();

    expect(fn () => svc()->confirm($b, $staff))->toThrow(BookingException::class);
    svc()->submitPayment($b, $b->total_amount, now(), UploadedFile::fake()->image('s2.jpg'));
    expect($b->fresh()->payment_status)->toBe('submitted')->and($b->payments()->count())->toBe(2);
});

it('สร้างข้อความ LINE ที่มีเลขจอง รอบเรือ และท่าขึ้นเรือ', function () {
    [$out] = ferryFixture();
    $b = svc()->create(bookingData(['outbound_trip_id' => $out->id]));
    $msg = svc()->lineMessage($b);

    expect($msg)->toContain($b->reference)->toContain('ท่า A')->toContain('2 คน');
});
