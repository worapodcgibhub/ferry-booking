<?php

use App\Livewire\Admin\BookingDetail;
use App\Livewire\Admin\Islands;
use App\Livewire\BookingFlow;
use App\Livewire\BookingStatus;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Port;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use App\Services\BookingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

// ชุดเทสต์ยืนยันเกณฑ์ "ทดสอบก่อนส่งงาน" ของเฟส 1 ผ่านหน้าจอ (Livewire) และบริการจอง

beforeEach(function () {
    $this->withoutVite();
    $this->seed(DatabaseSeeder::class);
    $this->staff = User::where('email', 'staff@demo.test')->first();
});

/** รอบเรือสาธิต ตราด ⇄ เกาะกูด ของวันที่ +5: [ไป, กลับ(รอบสุดท้ายของวัน), วันที่, ท่าต้นทาง, ท่าปลายทาง] */
function acceptanceTrips(): array
{
    $trat = Port::where('name', 'ท่าเรือตราด')->first();
    $kood = Port::where('name', 'ท่าเรือเกาะกูด')->first();
    $go = Route::where('origin_port_id', $trat->id)->where('destination_port_id', $kood->id)->first();
    $back = Route::where('origin_port_id', $kood->id)->where('destination_port_id', $trat->id)->first();
    $day = now()->addDays(5)->toDateString();
    $out = Trip::where('route_id', $go->id)->whereDate('departs_at', $day)->orderBy('departs_at')->first();
    $ret = Trip::where('route_id', $back->id)->whereDate('departs_at', $day)->orderBy('departs_at')->get()->last();

    return [$out, $ret, $day, $trat, $kood];
}

function acceptanceBooking(array $o = []): Booking
{
    return app(BookingService::class)->create(array_merge([
        'passengers' => 2, 'contact_name' => 'ทดสอบ', 'contact_phone' => '0811111111',
        'contact_email' => 'acc@example.test', 'idempotency_key' => bin2hex(random_bytes(8)),
    ], $o));
}

// ---------- ข้อ 2: เที่ยวเดียวครบกระบวนการ (ผ่านหน้าจอ) ----------
it('จองเที่ยวเดียวผ่านหน้าจอ แจ้งโอน ตรวจยอด และยืนยันครบกระบวนการ', function () {
    Storage::fake('local');
    Mail::fake();
    [$out, , $day, $trat, $kood] = acceptanceTrips();

    Livewire::test(BookingFlow::class, ['origin' => $trat->id, 'dest' => $kood->id, 'type' => 'oneway', 'date' => $day, 'pax' => 3])
        ->call('chooseOutbound', $out->id)->assertSet('step', 'details')
        ->set('contactName', 'เที่ยวเดียว ทดสอบ')->set('contactPhone', '0899999999')->set('contactEmail', 'oneway@example.test')->call('submit');

    $b = Booking::firstOrFail();
    expect($b->trip_type)->toBe('oneway')->and($b->legs)->toHaveCount(1)->and($b->total_amount)->toBe(3 * 450)
        ->and($out->fresh()->seats_taken)->toBe(3);

    Livewire::test(BookingStatus::class, ['token' => $b->access_token])
        ->set('amount', $b->total_amount)->set('transferredAt', now()->subMinute()->format('Y-m-d\TH:i'))
        ->set('slip', UploadedFile::fake()->image('slip.jpg'))->call('submitPayment')->assertHasNoErrors();
    expect($b->fresh()->payment_status)->toBe('submitted');

    $this->actingAs($this->staff);
    Livewire::test(BookingDetail::class, ['booking' => $b])->call('confirm');
    expect($b->fresh()->booking_status)->toBe('confirmed')->and($b->fresh()->payment_status)->toBe('verified');
    Mail::assertSent(BookingConfirmedMail::class, fn ($m) => $m->hasTo('oneway@example.test') && $m->booking->reference === $b->reference);
    Livewire::test(BookingStatus::class, ['token' => $b->access_token])->assertSee('ยืนยันการจองแล้ว');
});

// ---------- ข้อ 4: ที่นั่งสุดท้ายผ่านหน้าจอง ----------
it('ที่นั่งสุดท้ายผ่านหน้าจอง: รายแรกได้ รายที่สองถูกให้เลือกรอบใหม่ และมีการจองเดียว', function () {
    [$out, , $day, $trat, $kood] = acceptanceTrips();
    $out->update(['capacity' => 1, 'seats_taken' => 0]);
    $q = ['origin' => $trat->id, 'dest' => $kood->id, 'type' => 'oneway', 'date' => $day, 'pax' => 1];
    $fill = fn ($c, $n) => $c->call('chooseOutbound', $out->id)->set('contactName', $n)->set('contactPhone', '0812345678')->set('contactEmail', "$n@example.test");

    // สองลูกค้าเปิดหน้าจองรอบเดียวกันพร้อมกัน (ทั้งคู่เห็นที่นั่งว่าง) แล้วกดส่งตามกัน
    $a = Livewire::test(BookingFlow::class, $q);
    $b = Livewire::test(BookingFlow::class, $q);
    $fill($a, 'a');
    $fill($b, 'b');
    $out->update(['seats_taken' => 0]); // ทั้งสองยังเห็นว่าว่าง
    $a->call('submit')->assertRedirect();
    $b->call('submit')->assertSet('step', 'outbound')->assertSet('outboundTripId', null)->assertSee('กรุณาเลือกรอบใหม่');

    expect(Booking::count())->toBe(1)->and($out->fresh()->seats_taken)->toBe(1);
});

// ---------- ข้อ 5: ยกเลิก/หมดเวลา ไป–กลับ คืนที่นั่งครบสองขา ----------
it('หลังบ้านยกเลิกการจองไป–กลับ คืนที่นั่งครบทั้งสองขา และกดซ้ำไม่คืนซ้ำ', function () {
    [$out, $ret] = acceptanceTrips();
    $b = acceptanceBooking(['outbound_trip_id' => $out->id, 'return_trip_id' => $ret->id, 'passengers' => 2]);
    expect($out->fresh()->seats_taken)->toBe(2)->and($ret->fresh()->seats_taken)->toBe(2);

    $this->actingAs($this->staff);
    $c = Livewire::test(BookingDetail::class, ['booking' => $b])->set('cancelReason', 'ลูกค้าขอยกเลิก')->call('cancelBooking')->assertSee('ยกเลิกการจองและคืนที่นั่งแล้ว');
    expect($b->fresh()->booking_status)->toBe('cancelled')->and($out->fresh()->seats_taken)->toBe(0)->and($ret->fresh()->seats_taken)->toBe(0);

    $c->call('cancelBooking'); // กดซ้ำ ต้องไม่คืนซ้ำ/ติดลบ และแจ้งว่ายกเลิกไม่ได้แล้ว
    expect($out->fresh()->seats_taken)->toBe(0)->and($ret->fresh()->seats_taken)->toBe(0);
});

it('หมดเวลาโอนของรายการไป–กลับ: หน้าสถานะคืนที่นั่งทั้งสองขา และไม่คืนซ้ำเมื่อเปิดอีกครั้ง', function () {
    [$out, $ret] = acceptanceTrips();
    $keep = acceptanceBooking(['outbound_trip_id' => $out->id, 'return_trip_id' => $ret->id, 'passengers' => 1]); // รายการอื่นที่ยังอยู่
    $b = acceptanceBooking(['outbound_trip_id' => $out->id, 'return_trip_id' => $ret->id, 'passengers' => 2]);
    expect($out->fresh()->seats_taken)->toBe(3)->and($ret->fresh()->seats_taken)->toBe(3);

    $b->update(['expires_at' => now()->subMinute()]);
    Livewire::test(BookingStatus::class, ['token' => $b->access_token])->assertSee('หมดเวลาแจ้งโอน');
    expect($b->fresh()->booking_status)->toBe('expired')->and($out->fresh()->seats_taken)->toBe(1)->and($ret->fresh()->seats_taken)->toBe(1);

    Livewire::test(BookingStatus::class, ['token' => $b->access_token])->assertSee('หมดเวลาแจ้งโอน');
    expect($out->fresh()->seats_taken)->toBe(1)->and($ret->fresh()->seats_taken)->toBe(1)
        ->and($keep->fresh()->booking_status)->toBe('pending');
});

// ---------- ข้อความไฟล์ใหญ่เกินอ่านง่าย (MB) ----------
it('ข้อความไฟล์ใหญ่เกินที่ขั้นอัปโหลดแสดงเป็น MB ไม่ใช่กิโลไบต์', function () {
    $v = Validator::make(
        ['files' => [UploadedFile::fake()->create('big.jpg', 5000)]],
        ['files.*' => config('livewire.temporary_file_upload.rules')],
    );
    expect($v->fails())->toBeTrue()->and($v->errors()->first('files.0'))->toBe('ไฟล์ต้องมีขนาดไม่เกิน 4 MB');
});

it('เลือกสลิปเกิน 4 MB: ถูกปฏิเสธทันทีด้วยข้อความ "ไฟล์ต้องมีขนาดไม่เกิน 4 MB" และกดแจ้งโอนไม่ได้', function () {
    Storage::fake('local');
    [$out] = acceptanceTrips();
    $b = acceptanceBooking(['outbound_trip_id' => $out->id, 'passengers' => 1]);

    $c = Livewire::test(BookingStatus::class, ['token' => $b->access_token])
        ->set('amount', $b->total_amount)->set('transferredAt', now()->subMinute()->format('Y-m-d\TH:i'))
        ->set('slip', UploadedFile::fake()->create('big.jpg', 5000, 'image/jpeg')) // ไฟล์ถูกปฏิเสธที่ขั้นอัปโหลด
        ->assertHasErrors('slip')->assertSee('ไฟล์ต้องมีขนาดไม่เกิน 4 MB')->assertDontSee('4096');
    $c->call('submitPayment')->assertHasErrors('slip');
    expect($b->fresh()->payment_status)->toBe('unpaid')->and($b->payments()->count())->toBe(0);
});

it('รูปปกเกาะเกิน 4 MB ถูกปฏิเสธด้วยข้อความเป็น MB (ตรงกับกฎอัปโหลดรวม)', function () {
    $this->actingAs(User::where('email', 'owner@demo.test')->first());
    Livewire::test(Islands::class)->call('create')->set('name', 'เกาะทดสอบ')
        ->set('cover', UploadedFile::fake()->create('c.jpg', 5000, 'image/jpeg'))
        ->assertHasErrors('cover')->assertSee('ไฟล์ต้องมีขนาดไม่เกิน 4 MB');
});

it('ถ้าเลี่ยงกฎขั้นอัปโหลดได้ ขั้นแจ้งโอนยังปฏิเสธสลิปเกิน 4 MB ด้วยข้อความ MB (กันเลี่ยง)', function () {
    Storage::fake('local');
    config(['livewire.temporary_file_upload.rules' => ['required', 'file']]); // จำลองว่าขั้นอัปโหลดไม่ได้ตรวจขนาด
    [$out] = acceptanceTrips();
    $b = acceptanceBooking(['outbound_trip_id' => $out->id, 'passengers' => 1]);

    Livewire::test(BookingStatus::class, ['token' => $b->access_token])
        ->set('amount', $b->total_amount)->set('transferredAt', now()->subMinute()->format('Y-m-d\TH:i'))
        ->set('slip', UploadedFile::fake()->create('big.jpg', 5000, 'image/jpeg'))->assertHasNoErrors()
        ->call('submitPayment')->assertHasErrors('slip')->assertSee('ไฟล์สลิปต้องไม่เกิน 4 MB');
    expect($b->fresh()->payment_status)->toBe('unpaid')->and($b->payments()->count())->toBe(0);
});
