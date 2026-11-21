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

## ติดตั้งบนเครื่องใหม่ (Windows + Laravel Herd)
**ต้องมี:** PHP 8.3 ขึ้นไป (ทดสอบกับ 8.5 ผ่าน Herd) · Composer 2 · Node.js 22 ขึ้นไป (ทดสอบกับ 26) + npm · MariaDB 11 ขึ้นไป (ทดสอบกับ 13) · Git

```powershell
git clone https://github.com/worapodcgibhub/ferry-booking.git
cd ferry-booking
composer install
npm ci
npm run build
copy .env.example .env
php artisan key:generate
```
1. สร้างฐานข้อมูลและผู้ใช้ (แก้รหัสผ่านใน `database/setup/mariadb.sql` ก่อนรัน) แล้วใส่ `DB_DATABASE` `DB_USERNAME` `DB_PASSWORD` ใน `.env`
2. `php artisan migrate --seed` (เครื่องใหม่ที่ฐานข้อมูลว่าง) — seed จะสร้างเกาะ ท่าเรือ รอบเรือ ภาพปกสาธิต และบัญชีสาธิต (เฉพาะ `APP_ENV=local`)
3. `php artisan storage:link`
4. ตั้ง `upload_max_filesize` **≥ 5M** (ดูหัวข้อ "ค่า PHP สำหรับอัปโหลดสลิป") — Herd: แก้ `php.ini` แล้ว `herd restart php`; `php artisan serve`: ใช้ `php-ini/ferry.ini` ให้อัตโนมัติ
5. เปิดใช้: Herd → โฟลเดอร์ใน Herd จะได้ **http://ferry-booking.test** · `php artisan serve` → **http://127.0.0.1:8000** · หลังบ้านที่ `/login`
6. (ตอนเดโม) เปิดอีกหน้าต่าง: `php artisan schedule:work` เพื่อคืนที่นั่งรายการหมดเวลา
7. ตรวจ: `php artisan test` (ต้องผ่านทั้งหมด)

## ย้ายข้อมูลจากเครื่องอื่น
ใช้เมื่อต้องการย้ายการจอง เกาะ และไฟล์สลิป/ภาพปกที่อัปโหลดไว้ ไปเครื่องใหม่ **ห้ามนำไฟล์ที่ export ขึ้น repo** (มีข้อมูลลูกค้าและสลิปจริง)
1. **เครื่องเดิม:** `mysqldump --single-transaction --routines --default-character-set=utf8mb4 -u <user> -p ferry_booking > ferry_booking.sql` แล้วคัดลอกโฟลเดอร์ `storage/app` (ทั้ง `public` และ `private`) เก็บเป็น zip นอกโฟลเดอร์โปรเจกต์
2. **เครื่องใหม่:** ติดตั้งตามหัวข้อด้านบนจนถึง `key:generate` และสร้างฐานข้อมูลเปล่า **อย่ารัน `migrate --seed`** (dump มีโครงสร้างและข้อมูลครบแล้ว)
3. นำเข้า: `mariadb --default-character-set=utf8mb4 -u <user> -p ferry_booking < ferry_booking.sql` (ต้องใช้ utf8mb4 ไม่เช่นนั้นภาษาไทยเพี้ยน)
4. คัดลอกเนื้อใน `storage-app` ทับ `storage/app` แล้วรัน `php artisan storage:link`
5. ใช้ `APP_KEY` เดิมจาก `.env` เครื่องเก่า (คัดลอกเฉพาะบรรทัด `APP_KEY=` ผ่านช่องทางที่ปลอดภัย ห้ามขึ้น repo) ถ้าใช้คีย์ใหม่ session/ข้อมูลที่เข้ารหัสไว้จะอ่านไม่ได้ (รหัสผ่านผู้ใช้ไม่ได้ผูกกับ APP_KEY)
6. เปิดหน้าแอดมิน ตรวจจำนวนการจองและเปิดสลิป/ภาพปกดูว่าแสดงครบ

## ค่า PHP สำหรับอัปโหลดสลิป (สำคัญ)
แอปจำกัดสลิปไม่เกิน **4 MB** (`config/ferry.php`) แต่ PHP ต้องรับไฟล์ได้อย่างน้อยเท่ากัน
ถ้า `upload_max_filesize` ต่ำกว่านี้ (ค่าเริ่มต้นของ PHP คือ 2M) สลิป 2–4 MB จะอัปโหลดไม่สำเร็จ

| วิธีรัน | สิ่งที่ต้องทำ |
|---|---|
| **Laravel Herd** | ตั้ง `upload_max_filesize` **≥ 5M** ใน `php.ini` ของ Herd (เปิดด้วย `herd ini` หรือดูที่ `~/.config/herd/bin/php85/php.ini`) แล้วรัน `herd restart php` — ค่านี้ใช้ร่วมกับทุกไซต์ใน Herd และ `.user.ini` ในโปรเจกต์ใช้ไม่ได้บน Herd |
| **`php artisan serve`** | ไม่ต้องตั้งเพิ่ม ระบบโหลด `php-ini/ferry.ini` ของโปรเจกต์ให้เอง (`upload_max_filesize = 5M`, `post_max_size = 8M`) |
| **Deploy จริง** | ตั้ง `upload_max_filesize` และ `post_max_size` **ไม่ต่ำกว่า 4M** (แนะนำ 5M และ 8M เผื่อส่วนหัว multipart) ถ้าใช้ nginx ให้ตั้ง `client_max_body_size` ≥ 8M ด้วย |

## บัญชีสาธิต (หลังบ้านที่ /login) — ใช้ทดสอบเท่านั้น
> ⚠ บัญชีและรหัสผ่านด้านล่างเป็น **ข้อมูลสมมติสำหรับเครื่อง local เท่านั้น ห้ามใช้หรือสร้างบนเซิร์ฟเวอร์จริง/สาธารณะเด็ดขาด** (ใครก็เดาได้เพราะอยู่ใน repo สาธารณะ) seeder จะสร้างเฉพาะเมื่อ `APP_ENV=local` หรือ `testing`
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
- ภาพปกเกาะกูด/เกาะหมากเป็นภาพถ่ายจริงจาก Wikimedia Commons (CC BY-SA 3.0 / CC BY 4.0) เก็บใน `database/seeders/demo-images/` พร้อม `CREDITS.md` และแสดงเครดิตท้ายเว็บ; แอดมินอัปโหลดรูปของตัวเองทับได้ที่ "เกาะและพื้นที่"
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
