<?php

use App\Livewire\Home;
use App\Models\Island;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// ภาพปกสาธิตเก็บใน repo (database/seeders/demo-images) แล้ว seeder คัดลอกเข้า storage ให้ migrate:fresh --seed แล้วภาพไม่หาย
// พร้อมแสดงเครดิตผู้ถ่ายและสัญญาอนุญาตท้ายหน้าเว็บ

beforeEach(fn () => $this->withoutVite());

it('ไฟล์ภาพสาธิตและ CREDITS.md อยู่ใน repo และเป็นไฟล์ JPEG จริง', function () {
    foreach (['koh-kood.jpg', 'koh-mak.jpg'] as $f) {
        $path = database_path('seeders/demo-images/'.$f);
        expect(is_file($path))->toBeTrue()->and(getimagesize($path)['mime'])->toBe('image/jpeg')->and(filesize($path))->toBeGreaterThan(50_000);
    }
    $credits = file_get_contents(database_path('seeders/demo-images/CREDITS.md'));
    expect($credits)->toContain('Sry85')->toContain('CC BY-SA 3.0')->toContain('Vyacheslav Argenberg')->toContain('CC BY 4.0');
});

it('seeder คัดลอกภาพเข้า storage และตั้งเป็นภาพปก แม้ล้างฐานข้อมูลแล้ว seed ใหม่', function () {
    Storage::fake('public');
    $this->seed(DatabaseSeeder::class);

    Storage::disk('public')->assertExists('covers/koh-kood.jpg');
    Storage::disk('public')->assertExists('covers/koh-mak.jpg');
    expect(Island::where('slug', 'koh-kood')->value('cover_path'))->toBe('covers/koh-kood.jpg')
        ->and(Island::where('slug', 'koh-mak')->value('cover_path'))->toBe('covers/koh-mak.jpg');

    // ไฟล์หายจาก storage (เช่น เครื่องใหม่) แล้ว seed ซ้ำ ต้องคัดลอกกลับมาให้ และไม่เขียนทับไฟล์ที่มีอยู่
    Storage::disk('public')->delete('covers/koh-kood.jpg');
    Storage::disk('public')->put('covers/koh-mak.jpg', 'ของแอดมิน');
    $this->seed(DatabaseSeeder::class);
    Storage::disk('public')->assertExists('covers/koh-kood.jpg');
    expect(Storage::disk('public')->get('covers/koh-mak.jpg'))->toBe('ของแอดมิน');
});

it('ท้ายหน้าเว็บแสดงเครดิตผู้ถ่ายและสัญญาอนุญาตของภาพที่ใช้อยู่', function () {
    Storage::fake('public');
    $this->seed(DatabaseSeeder::class);

    $this->get('/')->assertOk()
        ->assertSee('เครดิตภาพปก')->assertSee('Sry85')->assertSee('CC BY-SA 3.0')
        ->assertSee('Vyacheslav Argenberg')->assertSee('CC BY 4.0')
        ->assertSee('commons.wikimedia.org', false)->assertSee('creativecommons.org/licenses/by/4.0/', false);
});

it('เกาะที่เปลี่ยนเป็นภาพของตัวเองแล้ว เครดิตของเกาะนั้นหายไป เกาะอื่นยังอยู่', function () {
    Storage::fake('public');
    $this->seed(DatabaseSeeder::class);
    Island::where('slug', 'koh-kood')->update(['cover_path' => 'covers/uploaded-by-admin.jpg']);

    $this->get('/')->assertOk()->assertDontSee('Sry85')->assertDontSee('CC BY-SA 3.0')->assertSee('Vyacheslav Argenberg');

    Island::where('slug', 'koh-mak')->update(['is_active' => false]);
    $this->get('/')->assertOk()->assertDontSee('เครดิตภาพปก'); // ไม่มีเกาะที่ใช้ภาพมีเครดิตเหลือ
});

it('หน้าแรกใช้ภาพปกจริงของเกาะเป็นภาพหลัก และสลับเกาะแล้วภาพเปลี่ยนตาม', function () {
    Storage::fake('public');
    $this->seed(DatabaseSeeder::class);
    $hero = fn ($c) => preg_match('/<img[^>]*data-hero-cover[^>]*src="([^"]+)"/', $c->html(), $m) ? $m[1] : null;

    $c = Livewire::test(Home::class);
    expect($hero($c))->toEndWith('storage/covers/koh-kood.jpg');
    $c->call('selectIsland', 'koh-mak');
    expect($hero($c))->toEndWith('storage/covers/koh-mak.jpg');
});
