<?php

use App\Livewire\Admin\BookingDetail;
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

// บั๊กเดิม: ช่องเหตุผล "ยอดไม่ตรง" กับ "ยกเลิก" ผูกตัวแปร Livewire ตัวเดียวกัน (reason) พิมพ์ช่องหนึ่งอีกช่องขึ้นตาม
// และปุ่มหนึ่งส่งข้อความของอีกช่องไปบันทึกผิดที่

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'staff@demo.test')->first());
});

/** การจองที่ลูกค้าแจ้งโอนแล้ว (รอตรวจยอด) — ปุ่มทั้ง "ขอหลักฐานใหม่" และ "ยกเลิก" แสดงพร้อมกัน */
function reviewBooking(): Booking
{
    $trat = Port::where('name', 'ท่าเรือตราด')->first();
    $kood = Port::where('name', 'ท่าเรือเกาะกูด')->first();
    $route = Route::where('origin_port_id', $trat->id)->where('destination_port_id', $kood->id)->first();
    $trip = Trip::where('route_id', $route->id)->where('departs_at', '>', now()->addDay())->orderBy('departs_at')->first();
    $svc = app(BookingService::class);
    $b = $svc->create(['outbound_trip_id' => $trip->id, 'passengers' => 2, 'contact_name' => 'ทดสอบ', 'contact_phone' => '0811111111', 'contact_email' => 'r@example.test', 'idempotency_key' => bin2hex(random_bytes(8))]);
    $svc->submitPayment($b, 100, now(), UploadedFile::fake()->image('s.jpg'));

    return $b->fresh();
}

it('สองช่องเหตุผลเป็นตัวแปรแยกกัน พิมพ์ช่องหนึ่งอีกช่องไม่เปลี่ยนตาม', function () {
    Livewire::test(BookingDetail::class, ['booking' => reviewBooking()])
        ->set('evidenceReason', 'ข้อความช่องยอดไม่ตรง')->assertSet('cancelReason', '')
        ->set('cancelReason', 'ข้อความช่องยกเลิก')->assertSet('evidenceReason', 'ข้อความช่องยอดไม่ตรง')->assertSet('cancelReason', 'ข้อความช่องยกเลิก');
});

it('หน้าจอมี label/placeholder ชัดเจนทั้งสองช่อง และผูกกับตัวแปรคนละตัว', function () {
    $c = Livewire::test(BookingDetail::class, ['booking' => reviewBooking()])
        ->assertSee('เหตุผลที่ยอดไม่ตรง (ลูกค้าจะเห็น)')->assertSee('เหตุผลการยกเลิก (บันทึกภายใน ลูกค้าไม่เห็น)')
        ->assertSeeHtml('wire:model="evidenceReason"')->assertSeeHtml('wire:model="cancelReason"')
        ->assertDontSeeHtml('wire:model="reason"');
    expect(substr_count($c->html(), 'wire:model="evidenceReason"'))->toBe(1)->and(substr_count($c->html(), 'wire:model="cancelReason"'))->toBe(1);
});

it('กดขอหลักฐานใหม่: ส่งเฉพาะเหตุผลของช่องตัวเอง ไม่ส่งค่าช่องยกเลิก และล้างเฉพาะช่องตัวเอง', function () {
    $b = reviewBooking();
    Livewire::test(BookingDetail::class, ['booking' => $b])
        ->set('evidenceReason', 'โอนมา 100 บาท ยอดจริง 900 บาท')->set('cancelReason', 'ข้อความของช่องยกเลิก')
        ->call('requestEvidence')->assertSee('ขอหลักฐานใหม่จากลูกค้าแล้ว')
        ->assertSet('evidenceReason', '')                          // ล้างช่องที่ใช้
        ->assertSet('cancelReason', 'ข้อความของช่องยกเลิก');         // อีกช่องไม่ถูกล้าง

    $b->refresh();
    expect($b->payments->first()->review_note)->toBe('โอนมา 100 บาท ยอดจริง 900 บาท') // ลูกค้าเห็นเฉพาะเหตุผลที่ถูกช่อง
        ->and($b->staff_note)->toBeNull()                                              // ค่าช่องยกเลิกไม่รั่วมาเป็นหมายเหตุ
        ->and($b->payment_status)->toBe('rejected')->and($b->booking_status)->toBe('pending');
});

it('กดยกเลิก: ส่งเฉพาะเหตุผลของช่องยกเลิก ไม่ใช้ค่าช่องยอดไม่ตรง และล้างเฉพาะช่องตัวเอง', function () {
    $b = reviewBooking();
    Livewire::test(BookingDetail::class, ['booking' => $b])
        ->set('evidenceReason', 'ข้อความที่ลูกค้าจะเห็น (ช่องยอดไม่ตรง)')->set('cancelReason', 'ลูกค้าขอยกเลิกทางโทรศัพท์')
        ->call('cancelBooking')->assertSee('ยกเลิกการจองและคืนที่นั่งแล้ว')
        ->assertSet('cancelReason', '')
        ->assertSet('evidenceReason', 'ข้อความที่ลูกค้าจะเห็น (ช่องยอดไม่ตรง)');

    $b->refresh();
    expect($b->staff_note)->toBe('ลูกค้าขอยกเลิกทางโทรศัพท์')
        ->and($b->payments->first()->review_note)->toBeNull()      // ไม่มีเหตุผล "ยอดไม่ตรง" ถูกบันทึกจากการกดยกเลิก
        ->and($b->booking_status)->toBe('cancelled');
});

it('เหตุผลยกเลิกที่เป็นช่องว่างล้วนไม่ถูกบันทึก และกดไม่สำเร็จจะไม่ล้างช่องที่พิมพ์ไว้', function () {
    $b = reviewBooking();
    Livewire::test(BookingDetail::class, ['booking' => $b])->set('cancelReason', '   ')->call('cancelBooking');
    expect($b->fresh()->staff_note)->toBeNull();

    // ขอหลักฐานใหม่กับรายการที่ยังไม่แจ้งโอนจะไม่สำเร็จ → ข้อความที่พิมพ์ต้องยังอยู่ให้แก้
    $trip = Trip::where('departs_at', '>', now()->addDay())->orderBy('id')->first();
    $unpaid = app(BookingService::class)->create(['outbound_trip_id' => $trip->id, 'passengers' => 1, 'contact_name' => 'x', 'contact_phone' => '0811111111', 'contact_email' => 'u@example.test', 'idempotency_key' => 'unpaid-1']);
    Livewire::test(BookingDetail::class, ['booking' => $unpaid])
        ->set('evidenceReason', 'ยังไม่ได้แจ้งโอน')->call('requestEvidence')
        ->assertSee('ทำรายการไม่ได้')->assertSet('evidenceReason', 'ยังไม่ได้แจ้งโอน');
});
