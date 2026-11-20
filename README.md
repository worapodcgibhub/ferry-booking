# เรือไปเกาะ — ระบบจองเรือหลายเกาะ (เฟส 1, ใช้ในเครื่อง)

Laravel 13 · Livewire 4 · Tailwind 4 · Pest · ฐานข้อมูล MariaDB (เทสต์ Pest ใช้ SQLite ในหน่วยความจำ)
ข้อมูลทั้งหมดเป็นข้อมูลสมมติ บัญชีรับโอนและสลิปเป็นของทดสอบ ไม่ส่งอีเมลจริง (บันทึกลง `storage/logs/laravel.log`)

## เปิดใช้งาน
```powershell
cd ferry-booking
php artisan serve          # เปิด http://127.0.0.1:8000
php artisan schedule:work  # (ไม่บังคับ) ให้ระบบคืนที่นั่งรายการหมดเวลาทุกนาที
```
ระบบยังคืนที่นั่งหมดเวลาให้เองทุกครั้งที่มีการจองใหม่หรือเปิดหน้าสถานะ และสั่งเองได้ด้วย `php artisan bookings:expire`

### ตัวจับเวลาหมดอายุตอนเดโม (`schedule:work`)
การจองที่ลูกค้าไม่แจ้งโอนภายใน 60 นาทีจะถูกคืนที่นั่งโดย `bookings:expire` ซึ่งตั้งให้รัน **ทุกนาที** ใน `routes/console.php`
ตอนเดโมให้เปิดเทอร์มินัลค้างไว้อีกหน้าต่าง (ในโฟลเดอร์โปรเจกต์) แล้วรัน:
```powershell
php artisan schedule:work     # รันงานตามเวลาทุกนาที จนกว่าจะกด Ctrl+C
php artisan schedule:list     # ดูว่ามีงานอะไรตั้งไว้ (ต้องเห็น bookings:expire ทุกนาที)
```
ทดลองให้เห็นผลเร็ว ๆ โดยไม่ต้องรอ 60 นาที: ตั้ง `FERRY_HOLD_MINUTES=1` ใน `.env` แล้วรัน `php artisan config:clear`
จากนั้นจองโดยไม่แจ้งโอน รอ 1–2 นาที รายการจะเปลี่ยนเป็น "หมดเวลา" และที่นั่งถูกคืน (อย่าลืมเอาค่านี้ออกหลังเดโม)
บน hosting จริงใช้ cron แทน: `* * * * * cd /path/to/ferry-booking && php artisan schedule:run >> /dev/null 2>&1`

ติดตั้งใหม่: `composer install && npm install && npm run build && copy .env.example .env && php artisan key:generate`
แล้วตั้ง DB ใน `.env` จากนั้น `php artisan migrate:fresh --seed` และ `php artisan storage:link`

## ค่า PHP สำหรับอัปโหลดสลิป (สำคัญ)
แอปจำกัดสลิปไม่เกิน **4 MB** (`config/ferry.php`) แต่ PHP ต้องรับไฟล์ได้อย่างน้อยเท่ากัน
ถ้า `upload_max_filesize` ต่ำกว่านี้ (ค่าเริ่มต้นของ PHP คือ 2M) สลิป 2–4 MB จะอัปโหลดไม่สำเร็จ

| วิธีรัน | สิ่งที่ต้องทำ |
|---|---|
| **Laravel Herd** | ตั้ง `upload_max_filesize` **≥ 5M** ใน `php.ini` ของ Herd (เปิดด้วย `herd ini` หรือดูที่ `~/.config/herd/bin/php85/php.ini`) แล้วรัน `herd restart php` — ค่านี้ใช้ร่วมกับทุกไซต์ใน Herd และ `.user.ini` ในโปรเจกต์ใช้ไม่ได้บน Herd |
| **`php artisan serve`** | ไม่ต้องตั้งเพิ่ม ระบบโหลด `php-ini/ferry.ini` ของโปรเจกต์ให้เอง (`upload_max_filesize = 5M`, `post_max_size = 8M`) |
| **Deploy จริง** | ตั้ง `upload_max_filesize` และ `post_max_size` **ไม่ต่ำกว่า 4M** (แนะนำ 5M และ 8M เผื่อส่วนหัว multipart) ถ้าใช้ nginx ให้ตั้ง `client_max_body_size` ≥ 8M ด้วย |

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
`./vendor/bin/pest` (หรือ `php artisan test`) — ใช้ SQLite ในหน่วยความจำ ไม่แตะฐานข้อมูลจริง

### ทดสอบการจองพร้อมกันแบบหลายโปรเซส (MariaDB)
เทสต์ Pest รันทีละคำขอ จึงพิสูจน์การจองพร้อมกันจริงไม่ได้ สคริปต์ `scripts/race-last-seat.php` สั่ง N โปรเซสทำงาน **พร้อมกัน**
(ทุกโปรเซสรอเวลาเริ่มร่วมกัน) แล้วตรวจผลลัพธ์ในฐานข้อมูล มี 3 สถานการณ์ (เลือกด้วย `--scenario=`, ค่าเริ่มต้น `all`):

| สถานการณ์ | สิ่งที่ตรวจ |
|---|---|
| `single` | เที่ยวเดียวแย่งที่นั่งสุดท้าย 1 ที่ → สำเร็จ 1 รายการ ที่เหลือถูกปฏิเสธ ไม่มี error |
| `roundtrip` (ก) | ไป–กลับหลายรายการพร้อมกันบนรอบเรือชุดเดียวกัน ที่นั่งสุดท้ายของขากลับ (หรือขาไปในรอบคู่) เหลือ 1 → สำเร็จ 1 รายการ และ **ไม่มีที่นั่งของอีกขาค้าง** (seats_taken = 1 พอดีทั้งสองขา) |
| `mixed` (ข) | งานผสมที่ล็อกรอบเรือสลับลำดับกัน (จองไป–กลับสุ่มคู่รอบ + ยกเลิก + คืนที่นั่งรายการหมดเวลา) → ต้องไม่มี deadlock/error ที่หลุดถึงผู้ใช้ และหลังจบ `seats_taken` ของทุกรอบเรือเท่ากับที่นั่งของการจองที่ยังไม่ถูกคืน ไม่เกินโควตา ไม่ติดลบ รายงานจำนวน retry ที่เกิดขึ้นด้วย |

1. สร้างฐานข้อมูลทดสอบแยก เช่น `ferry_booking_test` (มีคำสั่งอยู่ใน `database/setup/mariadb.sql`)
2. ตั้งค่าเชื่อมต่อผ่านตัวแปรสภาพแวดล้อม แล้วรัน (ตัวอย่าง PowerShell):
```powershell
$env:DB_CONNECTION='mariadb'; $env:DB_DATABASE='ferry_booking_test'
$env:DB_USERNAME='ferry_booking'; $env:DB_PASSWORD='รหัสผ่านของคุณ'
php scripts/race-last-seat.php --scenario=all --processes=8 --rounds=20   # ใช้เวลาประมาณ 3–5 นาที
php scripts/race-last-seat.php --scenario=roundtrip --rounds=5             # รันเฉพาะสถานการณ์เดียว
```
exit code: `0` = ผ่านทุกรอบ, `1` = มีรอบไม่ผ่าน (แสดงรายละเอียดรอบนั้น), `2` = ไม่ได้รัน (ตั้งค่าไม่ปลอดภัย)
> ⚠ สคริปต์ล้างตารางทั้งฐาน (`migrate:fresh`) จึง **ปฏิเสธการรัน** ถ้าไม่ใช่ MariaDB/MySQL หรือชื่อฐานข้อมูลไม่ลงท้ายด้วย `_test`
> (กันเผลอรันกับฐานข้อมูลจริง) ใช้ SQLite ไม่ได้เพราะแชร์ข้ามโปรเซสไม่ได้
