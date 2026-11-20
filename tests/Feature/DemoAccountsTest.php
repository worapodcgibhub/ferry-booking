<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeder สร้างบัญชีสาธิตเฉพาะ local และ testing', function (string $env, bool $expected) {
    app()->detectEnvironment(fn () => $env);
    (new DatabaseSeeder)->run(); // เรียกตรง ๆ (คำสั่ง db:seed บน production จะถามยืนยันก่อนอยู่แล้ว)

    expect(User::whereIn('email', ['owner@demo.test', 'staff@demo.test'])->count())->toBe($expected ? 2 : 0);
    // ข้อมูลสาธิตอื่น (เกาะ/รอบเรือ) ยังถูกสร้างตามปกติ
    expect(\App\Models\Island::count())->toBeGreaterThan(0);
})->with([
    'local' => ['local', true],
    'testing' => ['testing', true],
    'production' => ['production', false],
    'staging' => ['staging', false],
]);
