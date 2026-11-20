# เรือไปเกาะ — ระบบจองเรือหลายเกาะ (เฟส 1, ใช้ในเครื่อง)

Laravel 13 · Livewire 4 · Tailwind 4 · Pest · ฐานข้อมูล MariaDB (ตอนนี้ใช้ SQLite ชั่วคราวระหว่างรอติดตั้ง MariaDB)
ข้อมูลทั้งหมดเป็นข้อมูลสมมติ บัญชีรับโอนและสลิปเป็นของทดสอบ ไม่ส่งอีเมลจริง (บันทึกลง `storage/logs/laravel.log`)

## เปิดใช้งาน
```powershell
cd ferry-booking
php artisan serve          # เปิด http://127.0.0.1:8000
php artisan schedule:work  # (ไม่บังคับ) ให้ระบบคืนที่นั่งรายการหมดเวลาทุกนาที
```
ระบบยังคืนที่นั่งหมดเวลาให้เองทุกครั้งที่มีการจองใหม่หรือเปิดหน้าสถานะ และสั่งเองได้ด้วย `php artisan bookings:expire`

ติดตั้งใหม่: `composer install && npm install && npm run build && copy .env.example .env && php artisan key:generate`
แล้วตั้ง DB ใน `.env` จากนั้น `php artisan migrate:fresh --seed` และ `php artisan storage:link`

## บัญชีสาธิต (หลังบ้านที่ /login) — ใช้ทดสอบเท่านั้น
> บัญชีเหล่านี้ใช้รหัสผ่านที่เดาง่าย **ใช้สำหรับทดสอบในเครื่องเท่านั้น** seeder จะสร้างเฉพาะเมื่อ `APP_ENV=local` หรือ `testing`
> บน production/staging จะไม่ถูกสร้าง ต้องสร้างเจ้าของระบบเองด้วยรหัสผ่านที่ปลอดภัย (เช่น `php artisan tinker`
> แล้ว `User::create(['name'=>..., 'email'=>..., 'password'=>..., 'role'=>'owner'])`)

| บทบาท | อีเมล | รหัสผ่าน |
|---|---|---|
| เจ้าของระบบ | owner@demo.test | password |
| เจ้าหน้าที่ | staff@demo.test | password |

## ย้ายไป MariaDB
1. ติดตั้ง/เปิด MariaDB แล้วรัน `database/setup/mariadb.sql` (แก้รหัสผ่านก่อน)
2. ใน `.env` ตั้ง `DB_CONNECTION=mariadb`, `DB_HOST`, `DB_PORT`, `DB_DATABASE=ferry_booking`, `DB_USERNAME`, `DB_PASSWORD`
3. `php artisan migrate:fresh --seed`

## สมมติฐานที่ตั้งไว้ (แก้ได้ใน `config/ferry.php`)
- ลูกค้ามีเวลาแจ้งโอน 60 นาทีหลังส่งคำขอ; ถ้าทีมงานขอหลักฐานใหม่ให้เวลา 24 ชั่วโมง
- จองได้ครั้งละไม่เกิน 10 คน; ราคาคิดต่อคนต่อขา; ขากลับต้องออกหลังเรือขาไปถึง
- รายการที่แนบสลิปทันเวลาจะไม่ถูกหมดเวลาอัตโนมัติ ทีมงานตรวจและยืนยันได้แม้เลยกำหนดโอน
- ยกเลิก/หมดเวลา คืนที่นั่งครบทุกขาครั้งเดียว (ป้องกันด้วย `seats_released_at`)
- กันที่นั่งด้วย UPDATE แบบ atomic (`seats_taken + n <= capacity`) ในธุรกรรมเดียวทุกขา
- ภาพปกเกาะกูด/เกาะหมากเป็น SVG สาธิตที่สร้างเอง; แอดมินอัปโหลดรูปจริงทับได้ที่ "เกาะและพื้นที่"
- Payment Gateway และการตรวจยอดอัตโนมัติอยู่เฟส 2

## ทดสอบ
`./vendor/bin/pest`
