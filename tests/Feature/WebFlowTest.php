<?php

use App\Livewire\Admin\BookingDetail;
use App\Livewire\Admin\Islands;
use App\Livewire\Admin\Ports;
use App\Livewire\Admin\Routes as AdminRoutes;
use App\Livewire\Admin\Trips as AdminTrips;
use App\Livewire\BookingFlow;
use App\Livewire\BookingStatus;
use App\Livewire\Home;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Island;
use App\Models\Port;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(DatabaseSeeder::class);
    $this->owner = User::where('email', 'owner@demo.test')->first();
    $this->staff = User::where('email', 'staff@demo.test')->first();
});

function koodTrips(int $minSeats = 1): array
{
    $trat = Port::where('name', 'ท่าเรือตราด')->first();
    $kood = Port::where('name', 'ท่าเรือเกาะกูด')->first();
    $go = Route::where('origin_port_id', $trat->id)->where('destination_port_id', $kood->id)->first();
    $back = Route::where('origin_port_id', $kood->id)->where('destination_port_id', $trat->id)->first();
    $day = now()->addDays(5)->toDateString();
    $out = Trip::where('route_id', $go->id)->whereDate('departs_at', $day)->orderBy('departs_at')->first();
    $ret = Trip::where('route_id', $back->id)->whereDate('departs_at', $day)->orderBy('departs_at')->get()->last();

    return [$go, $out, $ret, $day, $trat, $kood];
}

function flow(array $q): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(BookingFlow::class, $q);
}

it('หน้าแรกแสดงเกาะกูดเป็นค่าเริ่มต้น และสลับเกาะแล้วเนื้อหาเปลี่ยนพร้อมกันโดยเก็บวันที่/จำนวนคนไว้', function () {
    $c = Livewire::test(Home::class)->set('departDate', now()->addDays(4)->toDateString())->set('passengers', 3)
        ->assertSee('ไปเกาะกูด')->assertSee('ท่าเรือเกาะกูด');

    $c->call('selectIsland', 'koh-mak')
        ->assertSee('ไปเกาะหมาก')->assertSee('ท่าเรือเกาะหมาก')->assertDontSee('ไปเกาะกูด')
        ->assertSet('departDate', now()->addDays(4)->toDateString())->assertSet('passengers', 3)
        ->assertSet('originId', Port::where('name', 'ท่าเรือแหลมงอบ')->value('id'));
});

it('สลับเกาะแล้วเก็บต้นทางเดิมไว้ถ้ายังมีเส้นทางไปเกาะนั้น', function () {
    $this->actingAs($this->owner);
    $third = Island::create(['name' => 'เกาะช้าง', 'slug' => 'koh-chang', 'sort_order' => 3]);
    $trat = Port::where('name', 'ท่าเรือตราด')->first();
    $changPort = Port::create(['name' => 'ท่าเรือเกาะช้าง', 'island_id' => $third->id]);
    Route::create(['origin_port_id' => $trat->id, 'destination_port_id' => $changPort->id, 'island_id' => $third->id, 'duration_minutes' => 45]);

    Livewire::test(Home::class)->assertSet('originId', $trat->id)
        ->call('selectIsland', 'koh-chang')->assertSet('originId', $trat->id)->assertSet('destinationId', $changPort->id)->assertSee('ไปเกาะช้าง');
});

it('แอดมินเพิ่มเกาะที่สาม พร้อมรูปปก ท่า เส้นทาง และเผยแพร่รอบเรือ แล้วลูกค้าเห็นและจองได้', function () {
    Storage::fake('public');
    $this->actingAs($this->owner);

    Livewire::test(Islands::class)->call('create')
        ->set('name', 'เกาะช้าง')->set('headline', 'ไปเกาะช้าง')
        ->set('cover', UploadedFile::fake()->image('cover.jpg', 1600, 700))->call('save')->assertHasNoErrors();
    $island = Island::where('name', 'เกาะช้าง')->firstOrFail();
    expect($island->cover_path)->toStartWith('covers/');
    Storage::disk('public')->assertExists($island->cover_path);

    Livewire::test(Ports::class)->call('create')->set('name', 'ท่าเรือเกาะช้าง')->set('islandId', $island->id)->call('save')->assertHasNoErrors();
    $chang = Port::where('name', 'ท่าเรือเกาะช้าง')->first();
    $trat = Port::where('name', 'ท่าเรือตราด')->first();

    Livewire::test(AdminRoutes::class)->call('create')->set('originId', $trat->id)->set('destinationId', $chang->id)
        ->set('islandId', $island->id)->set('duration', 45)->call('save')->assertHasNoErrors();
    expect(Route::count())->toBe(6); // 4 เดิม + ไป-กลับของเกาะช้าง
    $route = Route::where('origin_port_id', $trat->id)->where('destination_port_id', $chang->id)->first();

    Livewire::test(AdminTrips::class)->set('routeId', $route->id)->set('fromDate', now()->addDay()->toDateString())
        ->set('toDate', now()->addDays(3)->toDateString())->set('times', '09:00, 13:00')->set('price', 300)->set('capacity', 20)
        ->call('generate')->assertHasNoErrors();
    expect(Trip::where('route_id', $route->id)->count())->toBe(6);

    auth()->logout();
    Livewire::test(Home::class)->call('selectIsland', 'koh-chang')->assertSee('ไปเกาะช้าง')->assertSee('ท่าเรือเกาะช้าง')
        ->set('tripType', 'oneway')->set('departDate', now()->addDay()->toDateString())->call('search')->assertRedirect();
});

it('จองไป–กลับ แจ้งโอน ตรวจยอด และยืนยันครบกระบวนการ พร้อมอีเมลเลขจอง', function () {
    Storage::fake('local');
    Mail::fake();
    [$go, $out, $ret, $day, $trat, $kood] = koodTrips();
    $q = ['origin' => $trat->id, 'dest' => $kood->id, 'type' => 'round', 'date' => $day, 'ret' => $day, 'pax' => 2];

    $c = flow($q)->call('chooseOutbound', $out->id)->assertSet('step', 'return')
        ->call('chooseReturn', $ret->id)->assertSet('step', 'details')
        ->set('contactName', 'สมชาย ใจดี')->set('contactPhone', '081-234-5678')->set('contactEmail', 'somchai@example.test')->call('submit');

    $booking = Booking::firstOrFail();
    $c->assertRedirect(route('booking.show', $booking->access_token));
    expect($booking->total_amount)->toBe(2 * 450 * 2)->and($booking->legs)->toHaveCount(2);

    // ลูกค้าแจ้งโอนผ่านลิงก์เฉพาะรายการ
    Livewire::test(BookingStatus::class, ['token' => $booking->access_token])
        ->set('amount', $booking->total_amount)->set('transferredAt', now()->subMinute()->format('Y-m-d\TH:i'))
        ->set('slip', UploadedFile::fake()->image('slip.png'))->call('submitPayment')->assertHasNoErrors();
    expect($booking->fresh()->payment_status)->toBe('submitted');

    // เจ้าหน้าที่ตรวจและยืนยัน
    $this->actingAs($this->staff);
    Livewire::test(BookingDetail::class, ['booking' => $booking])->call('confirm')->assertSee('ยืนยันการจองแล้ว');
    expect($booking->fresh()->booking_status)->toBe('confirmed');
    Mail::assertSent(BookingConfirmedMail::class, fn ($m) => $m->hasTo('somchai@example.test'));
});

it('ขากลับเต็มตอนส่งคำขอ: แจ้งให้เลือกรอบใหม่ และไม่มีขาไปค้าง', function () {
    [$go, $out, $ret, $day, $trat, $kood] = koodTrips();
    $q = ['origin' => $trat->id, 'dest' => $kood->id, 'type' => 'round', 'date' => $day, 'ret' => $day, 'pax' => 2];

    $c = flow($q)->call('chooseOutbound', $out->id)->call('chooseReturn', $ret->id)
        ->set('contactName', 'ก')->set('contactPhone', '0812345678')->set('contactEmail', 'a@example.test');
    $ret->update(['seats_taken' => $ret->capacity]); // ระหว่างกรอกฟอร์ม มีคนจองขากลับเต็มพอดี
    $c->call('submit')->assertSet('step', 'return')->assertSet('returnTripId', null)->assertSee('กรุณาเลือกรอบใหม่');

    expect(Booking::count())->toBe(0)->and($out->fresh()->seats_taken)->toBe(0);
});

it('กดส่งคำขอซ้ำได้เลขจองเดียว', function () {
    [$go, $out, $ret, $day, $trat, $kood] = koodTrips();
    $q = ['origin' => $trat->id, 'dest' => $kood->id, 'type' => 'oneway', 'date' => $day, 'pax' => 3];

    $c = flow($q)->call('chooseOutbound', $out->id)
        ->set('contactName', 'ข')->set('contactPhone', '0812345678')->set('contactEmail', 'b@example.test');
    $c->call('submit');
    $c->call('submit'); // ผู้ใช้กดซ้ำ/รีเฟรชแล้วส่งซ้ำ ด้วยคีย์เดิม

    expect(Booking::count())->toBe(1)->and($out->fresh()->seats_taken)->toBe(3);
});

it('สลิปเปิดได้เฉพาะเจ้าหน้าที่ที่ล็อกอิน หรือเจ้าของลิงก์เฉพาะรายการ', function () {
    Storage::fake('local');
    [$go, $out, , $day] = koodTrips();
    $svc = app(App\Services\BookingService::class);
    $mk = fn ($key) => $svc->create(['outbound_trip_id' => $out->id, 'passengers' => 1, 'contact_name' => 'x', 'contact_phone' => '0811111111', 'contact_email' => 'x@example.test', 'idempotency_key' => $key]);
    $a = $mk('a'); $b = $mk('b');
    $pa = $svc->submitPayment($a, $a->total_amount, now(), UploadedFile::fake()->createWithContent('a.png', 'SLIP-A'));
    $pb = $svc->submitPayment($b, $b->total_amount, now(), UploadedFile::fake()->createWithContent('b.png', 'SLIP-B'));

    // ผู้ใช้ทั่วไป (ยังไม่ล็อกอิน) ถูกส่งไปหน้าล็อกอิน
    $this->get(route('admin.slip', $pa))->assertRedirect(route('login'));
    // เลขจองอย่างเดียวเปิดหน้าส่วนตัว/สลิปไม่ได้
    $this->get('/booking/'.$a->reference)->assertNotFound();
    $this->get('/booking/'.$a->reference.'/slip')->assertNotFound();
    // เจ้าของลิงก์ A เห็นของ A เท่านั้น (ลิงก์ A ไม่ให้ไฟล์ของ B)
    expect($this->get(route('booking.slip', $a->access_token))->assertOk()->streamedContent())->toBe('SLIP-A');
    expect($this->get(route('booking.slip', $b->access_token))->assertOk()->streamedContent())->toBe('SLIP-B');
    $this->get('/booking/'.str_repeat('z', 48).'/slip')->assertNotFound();

    // เจ้าหน้าที่ที่มีสิทธิ์เปิดได้
    $this->actingAs($this->staff)->get(route('admin.slip', $pa))->assertOk();
    // ไฟล์สลิปไม่อยู่ใน public disk
    expect(Storage::disk('public')->exists($pa->slip_path))->toBeFalse();
});

it('เจ้าหน้าที่เข้าหน้าตรวจยอดได้ แต่ไม่เข้าหน้าตั้งค่าของเจ้าของระบบ', function () {
    $this->get(route('admin.bookings'))->assertRedirect(route('login'));
    $this->actingAs($this->staff);
    $this->get(route('admin.dashboard'))->assertOk();
    $this->get(route('admin.bookings'))->assertOk();
    $this->get(route('admin.passengers'))->assertOk();
    foreach (['admin.islands', 'admin.ports', 'admin.routes', 'admin.trips', 'admin.users'] as $r) {
        $this->get(route($r))->assertForbidden();
    }
    $this->actingAs($this->owner);
    foreach (['admin.islands', 'admin.ports', 'admin.routes', 'admin.trips', 'admin.users'] as $r) {
        $this->get(route($r))->assertOk();
    }
});

it('หน้าแรก หน้าเลือกรอบ และล็อกอินแสดงผลได้', function () {
    $this->get('/')->assertOk()->assertSee('ไปเกาะกูด');
    $this->get('/?island=koh-mak')->assertOk()->assertSee('ไปเกาะหมาก');
    $this->get(route('login'))->assertOk();
    $this->post(route('login.attempt'), ['email' => 'staff@demo.test', 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
});
