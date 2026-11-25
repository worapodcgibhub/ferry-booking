<?php

use App\Livewire\BookingLookup;
use App\Models\Booking;
use App\Models\Route;
use App\Models\Trip;
use App\Services\BookingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

// ตรวจสอบการจอง: เลขอ้างอิง + (เบอร์หรืออีเมล) → หน้าสถานะ

const LOOKUP_MISS = 'ไม่พบการจองที่ตรงกับข้อมูลนี้';

beforeEach(function () {
    $this->withoutVite();
    $this->seed(DatabaseSeeder::class);
    RateLimiter::clear('lookup:ip:127.0.0.1');
});

function lookupBooking(array $o = []): Booking
{
    $trip = Trip::where('departs_at', '>', now()->addDay())->orderBy('departs_at')->firstOrFail();

    return app(BookingService::class)->create(array_merge([
        'outbound_trip_id' => $trip->id, 'passengers' => 1, 'contact_name' => 'ทดสอบ',
        'contact_phone' => '081-234-5678', 'contact_email' => 'Look@Example.Test',
        'idempotency_key' => bin2hex(random_bytes(8)),
    ], $o));
}

function lookup(string $ref, string $contact)
{
    return Livewire::test(BookingLookup::class)->set('reference', $ref)->set('contact', $contact)->call('search');
}

it('เลขอ้างอิง + เบอร์ที่ตรง พาไปหน้าสถานะของการจองนั้น (เบอร์มีขีด/ช่องว่าง/+66 ก็ตรง)', function ($contact) {
    $b = lookupBooking();
    lookup($b->reference, $contact)->assertRedirect(route('booking.show', $b->access_token));
})->with(['081-234-5678', '0812345678', ' 081 234 5678 ', '+66 81-234-5678', '66812345678']);

it('เลขอ้างอิง + อีเมลที่ตรง (ไม่สนตัวพิมพ์) พาไปหน้าสถานะ และเลขอ้างอิงพิมพ์เล็กได้', function () {
    $b = lookupBooking();
    lookup(strtolower($b->reference), 'look@example.test')->assertRedirect(route('booking.show', $b->access_token));
});

it('ข้อมูลที่สองผิด / เลขอ้างอิงไม่มีอยู่ / เบอร์ว่างหลังตัดอักขระ → ข้อความเดียวกันทุกกรณี และไม่ redirect', function () {
    $b = lookupBooking();
    foreach ([[$b->reference, '0899999999'], [$b->reference, 'other@example.test'], ['FB000000-XXXXX', '081-234-5678'], [$b->reference, '---']] as [$ref, $contact]) {
        RateLimiter::clear('lookup:ip:127.0.0.1');
        lookup($ref, $contact)->assertNoRedirect()->assertSee(LOOKUP_MISS);
    }
});

it('ข้อความเมื่อไม่ตรงเหมือนกันทุกตัวอักษร ระหว่าง "เลขมีแต่ข้อมูลผิด" กับ "ไม่มีเลขนี้"', function () {
    $b = lookupBooking();
    $a = lookup($b->reference, '0899999999')->get('error');
    RateLimiter::clear('lookup:ip:127.0.0.1');
    $c = lookup('FB000000-XXXXX', '0899999999')->get('error');
    expect($a)->toBe($c)->and($a)->toContain(LOOKUP_MISS);
});

it('ลองเกิน 5 ครั้งใน 10 นาทีถูกบล็อก แม้ครั้งถัดไปจะกรอกถูก และบอกว่าต้องรอกี่นาที', function () {
    $b = lookupBooking();
    $c = Livewire::test(BookingLookup::class);
    for ($i = 0; $i < 5; $i++) {
        $c->set('reference', $b->reference)->set('contact', '0800000000')->call('search')->assertSee(LOOKUP_MISS);
    }
    $c->set('reference', $b->reference)->set('contact', '081-234-5678')->call('search')
        ->assertNoRedirect()->assertSee('ลองค้นหาหลายครั้งเกินไป')->assertSee('10 นาที');
});

it('นับรวมครั้งที่ถูกด้วย: ค้นถูก 5 ครั้งแล้วครั้งที่ 6 ถูกบล็อก', function () {
    $b = lookupBooking();
    for ($i = 0; $i < 5; $i++) {
        lookup($b->reference, '0812345678')->assertRedirect();
    }
    lookup($b->reference, '0812345678')->assertNoRedirect()->assertSee('ลองค้นหาหลายครั้งเกินไป');
});

it('จำกัดตามเลขอ้างอิงด้วย: เปลี่ยน IP แล้วเลขเดิมก็ยังถูกบล็อก', function () {
    $b = lookupBooking();
    for ($i = 0; $i < 5; $i++) {
        lookup($b->reference, '0800000000');
    }
    RateLimiter::clear('lookup:ip:127.0.0.1'); // จำลองว่าเป็นอีก IP
    lookup($b->reference, '0812345678')->assertNoRedirect()->assertSee('ลองค้นหาหลายครั้งเกินไป');
});

it('เมนูบน (เดสก์ท็อป+มือถือ) และ footer มีลิงก์ตรวจสอบการจอง / แจ้งโอนเงิน', function () {
    $html = $this->get('/')->assertOk()->getContent();
    expect(substr_count($html, route('booking.lookup')))->toBeGreaterThanOrEqual(3)
        ->and($html)->toContain('ตรวจสอบการจอง / แจ้งโอนเงิน');
    $this->get(route('booking.lookup'))->assertOk()->assertSee('เลขอ้างอิงการจอง');
});

it('Booking::normalizePhone แปลง +66/66 เป็น 0 และตัดอักขระ', function () {
    expect(Booking::normalizePhone('+66 81-234-5678'))->toBe('0812345678')
        ->and(Booking::normalizePhone('(081) 234 5678'))->toBe('0812345678')
        ->and(Booking::normalizePhone('0812345678'))->toBe('0812345678')
        ->and(Booking::normalizePhone('abc'))->toBe('');
});
