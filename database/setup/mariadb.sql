-- ฐานข้อมูลเฉพาะโปรเจกต์ ferry-booking เท่านั้น (ไม่แตะฐานข้อมูลอื่น)
-- รันด้วยบัญชี admin ของ MariaDB แล้วเปลี่ยนรหัสผ่านก่อนใช้
CREATE DATABASE IF NOT EXISTS ferry_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ferry_booking'@'localhost' IDENTIFIED BY 'เปลี่ยนรหัสผ่านตรงนี้';
GRANT ALL PRIVILEGES ON ferry_booking.* TO 'ferry_booking'@'localhost';

-- ฐานข้อมูลสำหรับทดสอบ (ใช้กับ scripts/race-last-seat.php และรัน Pest บน MariaDB) ชื่อต้องลงท้าย _test
CREATE DATABASE IF NOT EXISTS ferry_booking_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON ferry_booking_test.* TO 'ferry_booking'@'localhost';
FLUSH PRIVILEGES;
