<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Island;
use App\Models\Port;
use App\Models\Route;
use App\Models\User;
use App\Services\TripGenerator;
use Illuminate\Database\Seeder;

/** ข้อมูลสาธิตทั้งหมดเป็นข้อมูลสมมติ ใช้ในเครื่องเท่านั้น */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // บัญชีสาธิตใช้รหัสผ่านที่เดาง่าย จึงสร้างเฉพาะ local และ testing เท่านั้น (ห้ามมีบน production/staging)
        // บน production ให้สร้างเจ้าของระบบเองด้วย `php artisan tinker` หรือคำสั่งของคุณ แล้วตั้งรหัสผ่านที่ปลอดภัย
        if (app()->environment('local', 'testing')) {
            User::updateOrCreate(['email' => 'owner@demo.test'], ['name' => 'เจ้าของระบบ (สาธิต)', 'password' => 'password', 'role' => 'owner']);
            User::updateOrCreate(['email' => 'staff@demo.test'], ['name' => 'เจ้าหน้าที่ (สาธิต)', 'password' => 'password', 'role' => 'staff']);
        }

        $area = Area::firstOrCreate(['slug' => 'trat'], ['name' => 'ตราด']);

        $kood = Island::firstOrCreate(['slug' => 'koh-kood'], [
            'area_id' => $area->id, 'name' => 'เกาะกูด', 'headline' => 'ไปเกาะกูด', 'sort_order' => 1,
            'cover_path' => 'images/covers/kood.svg',
            'description' => 'เกาะใหญ่อันดับสองของไทย ทะเลใส หาดขาว',
        ]);
        $mak = Island::firstOrCreate(['slug' => 'koh-mak'], [
            'area_id' => $area->id, 'name' => 'เกาะหมาก', 'headline' => 'ไปเกาะหมาก', 'sort_order' => 2,
            'cover_path' => 'images/covers/mak.svg',
            'description' => 'เกาะเงียบสงบ เหมาะพักผ่อนช้า ๆ',
        ]);

        $trat = Port::firstOrCreate(['name' => 'ท่าเรือตราด'], [
            'area_id' => $area->id, 'location' => 'อ.เมืองตราด จ.ตราด', 'boarding_point' => 'จุดขึ้นเรือไปเกาะกูด',
            'note' => 'มีที่จอดรถ ร้านค้า และห้องน้ำ', 'map_url' => 'https://maps.google.com/?q=Trat+pier',
        ]);
        $laem = Port::firstOrCreate(['name' => 'ท่าเรือแหลมงอบ'], [
            'area_id' => $area->id, 'location' => 'อ.แหลมงอบ จ.ตราด', 'boarding_point' => 'จุดขึ้นเรือไปเกาะหมาก',
            'note' => 'มีที่จอดรถและร้านอาหาร', 'map_url' => 'https://maps.google.com/?q=Laem+Ngop+pier',
        ]);
        $koodPort = Port::firstOrCreate(['name' => 'ท่าเรือเกาะกูด'], [
            'area_id' => $area->id, 'island_id' => $kood->id, 'location' => 'เกาะกูด จ.ตราด', 'boarding_point' => 'จุดขึ้นเรือกลับตราด',
            'note' => 'ใกล้ที่พักและบริการรถรับส่ง', 'map_url' => 'https://maps.google.com/?q=Koh+Kood+pier',
        ]);
        $makPort = Port::firstOrCreate(['name' => 'ท่าเรือเกาะหมาก'], [
            'area_id' => $area->id, 'island_id' => $mak->id, 'location' => 'เกาะหมาก จ.ตราด', 'boarding_point' => 'จุดขึ้นเรือกลับแหลมงอบ',
            'note' => 'ใกล้ร้านอาหารและรถรับส่ง', 'map_url' => 'https://maps.google.com/?q=Koh+Mak+pier',
        ]);

        $gen = new TripGenerator;
        $from = now()->startOfDay();
        $to = now()->addDays(45)->startOfDay();

        $defs = [
            [$trat, $koodPort, $kood, 70, ['08:00', '11:00', '14:00'], 450, 40],
            [$koodPort, $trat, $kood, 70, ['10:00', '13:00', '16:00'], 450, 40],
            [$laem, $makPort, $mak, 90, ['08:30', '12:00', '15:00'], 400, 30],
            [$makPort, $laem, $mak, 90, ['10:30', '13:30', '16:30'], 400, 30],
        ];

        foreach ($defs as [$o, $d, $island, $minutes, $times, $price, $capacity]) {
            $route = Route::firstOrCreate(
                ['origin_port_id' => $o->id, 'destination_port_id' => $d->id],
                ['island_id' => $island->id, 'duration_minutes' => $minutes],
            );
            $gen->generate($route, $from, $to, $times, $price, $capacity);
        }
    }
}
