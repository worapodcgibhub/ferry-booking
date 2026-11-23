<?php

use App\Livewire\Home;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(DatabaseSeeder::class);
});

function day(int $n): string
{
    return now()->addDays($n)->toDateString();
}

it('ค่าเริ่มต้นตอนโหลดหน้า: วันกลับ = วันไป+1 และยังไม่ถือว่าผู้ใช้แตะ', function () {
    Livewire::test(Home::class)->assertSet('departDate', day(7))->assertSet('returnDate', day(8))->assertSet('returnTouched', false);
});

it('ช่องวันกลับมี min เท่ากับวันไป และเปลี่ยนตามเมื่อเลือกวันไปใหม่', function () {
    Livewire::test(Home::class)->assertSeeHtml('min="'.day(7).'"')
        ->set('departDate', day(20))->assertSeeHtml('id="d2"')->assertSeeHtml('min="'.day(20).'"');
});

it('ยังไม่แตะวันกลับ: เปลี่ยนวันไปกี่ครั้ง วันกลับ = วันไป+1 เสมอ (รวมกรณีวันกลับเดิมยังไม่ก่อนวันไป)', function () {
    Livewire::test(Home::class)
        ->set('departDate', day(3))->assertSet('returnDate', day(4))   // วันกลับเดิม day(8) ยังหลังวันไป แต่ผู้ใช้ไม่เคยแตะ
        ->set('departDate', day(30))->assertSet('returnDate', day(31))
        ->set('departDate', day(12))->assertSet('returnDate', day(13))
        ->assertSet('returnTouched', false);
});

it('แตะวันกลับเองแล้วเปลี่ยนวันไป: ไม่เขียนทับถ้าวันกลับยังไม่ก่อนวันไป', function () {
    Livewire::test(Home::class)->set('returnDate', day(40))->assertSet('returnTouched', true)
        ->set('departDate', day(10))->assertSet('returnDate', day(40))
        ->set('departDate', day(25))->assertSet('returnDate', day(40));
});

it('แตะวันกลับเองแล้ว วันไปเลยวันกลับ: ขยับวันกลับเป็นวันไป+1', function () {
    Livewire::test(Home::class)->set('returnDate', day(12))
        ->set('departDate', day(50))->assertSet('returnDate', day(51))->assertSet('returnTouched', true);
});

it('แตะวันกลับเองแล้วล้างค่า (ว่าง) แล้วเปลี่ยนวันไป: ตั้งเป็นวันไป+1', function () {
    Livewire::test(Home::class)->set('returnDate', '')->set('departDate', day(15))->assertSet('returnDate', day(16));
});

it('ยังอนุญาตให้กลับวันเดียวกับวันไป เมื่อผู้ใช้เลือกเอง', function () {
    Livewire::test(Home::class)->set('returnDate', day(10))
        ->set('departDate', day(10))->assertSet('returnDate', day(10))->call('search')->assertHasNoErrors('returnDate');
});

it('สลับเที่ยวเดียว→ไป-กลับ ตอนยังไม่แตะวันกลับ: วันกลับ = วันไป+1', function () {
    Livewire::test(Home::class)->set('tripType', 'oneway')->set('departDate', day(30))
        ->assertSet('returnDate', day(8))                                // โหมดเที่ยวเดียวไม่แตะวันกลับ
        ->set('tripType', 'round')->assertSet('returnDate', day(31));
});

it('เที่ยวเดียว: เปลี่ยนวันไปแล้วไม่แตะวันกลับ และค้นหาได้โดยไม่ต้องมีวันกลับ', function () {
    Livewire::test(Home::class)->set('tripType', 'oneway')->set('returnDate', null)->set('departDate', day(6))
        ->assertSet('returnDate', null)->call('search')->assertHasNoErrors()->assertRedirect();
});

it('ฝั่งเซิร์ฟเวอร์ยังบังคับว่าวันกลับห้ามก่อนวันไป (ไม่พึ่งเบราว์เซอร์)', function () {
    $c = Livewire::test(Home::class)->set('departDate', day(10));
    $c->set('returnDate', day(8))->call('search')->assertHasErrors(['returnDate' => 'after_or_equal']);
    $c->set('returnDate', '')->call('search')->assertHasErrors('returnDate');
});

it('วันไปต้องไม่ก่อนวันนี้ยังคงเดิม', function () {
    Livewire::test(Home::class)->set('departDate', day(-1))->call('search')->assertHasErrors(['departDate' => 'after_or_equal']);
});
