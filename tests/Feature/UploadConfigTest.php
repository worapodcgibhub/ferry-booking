<?php

use Illuminate\Foundation\Console\ServeCommand;

// ปัญหาเดิม: `php artisan serve` บน Windows ไม่ส่ง TEMP/TMP ให้เซิร์ฟเวอร์ PHP จึงเขียนไฟล์ชั่วคราวไม่ได้
// สลิปทุกขนาดอัปโหลดไม่สำเร็จ ("The slip failed to upload.")
it('artisan serve ส่งต่อ TEMP/TMP และ ini เพิ่มเติมให้เซิร์ฟเวอร์', function () {
    expect(ServeCommand::$passthroughVariables)->toContain('TEMP', 'TMP', 'PHP_INI_SCAN_DIR');
});

it('เพดานสลิปสอดคล้องกันทุกชั้นที่ 4 MB และ PHP ในเครื่องรับได้อย่างน้อยเท่ากัน', function () {
    expect(config('ferry.slip_max_kb'))->toBe(4096)
        ->and(config('livewire.temporary_file_upload.rules'))->toContain('max:4096');

    $ini = parse_ini_file(base_path('php-ini/ferry.ini'));
    expect((int) $ini['upload_max_filesize'])->toBeGreaterThanOrEqual(4)
        ->and((int) $ini['post_max_size'])->toBeGreaterThan((int) $ini['upload_max_filesize']);
});

it('มีข้อความ validation ภาษาไทยสำหรับการอัปโหลดล้มเหลว', function () {
    expect(trans('validation.uploaded', ['attribute' => 'สลิป']))->toContain('อัปโหลด')->not->toBe('validation.uploaded');
});

it('สลิปที่เก็บอยู่ในดิสก์ส่วนตัวเท่านั้น (ไม่ใช่ public)', function () {
    expect(config('filesystems.disks.local.root'))->toContain('private')
        ->and(config('filesystems.disks.local.serve'))->not->toBeFalse() // โหลดผ่าน SlipController ที่ตรวจสิทธิ์เท่านั้น
        ->and(config('filesystems.disks.public.root'))->not->toBe(config('filesystems.disks.local.root'));
});
