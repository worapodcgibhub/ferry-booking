<?php

use App\Models\Island;

// ปัญหาเดิม: Storage::url() ใช้ APP_URL (localhost) รูปปกที่อัปโหลดจึงโหลดไม่ขึ้นเมื่อเปิดเว็บจาก IP/มือถือ
it('URL รูปปกใช้โฮสต์ของคำขอปัจจุบัน ไม่ผูกกับ APP_URL', function () {
    config(['app.url' => 'http://localhost:8000']);
    $uploaded = new Island(['cover_path' => 'covers/abc.jpg']);
    $bundled = new Island(['cover_path' => 'images/covers/kood.svg']);

    $this->get('http://192.168.1.50:8000/up'); // ตั้ง request host ปัจจุบัน
    expect($uploaded->cover_url)->toContain('/storage/covers/abc.jpg')->not->toContain('localhost')
        ->and($bundled->cover_url)->toContain('/images/covers/kood.svg')
        ->and((new Island)->cover_url)->toContain('/images/covers/default.svg');
});
