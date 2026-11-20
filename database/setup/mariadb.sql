-- ฐานข้อมูลเฉพาะโปรเจกต์ ferry-booking เท่านั้น (ไม่แตะฐานข้อมูลอื่น)
-- รันด้วยบัญชี admin ของ MariaDB แล้วเปลี่ยนรหัสผ่านก่อนใช้
CREATE DATABASE IF NOT EXISTS ferry_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ferry_booking'@'localhost' IDENTIFIED BY 'เปลี่ยนรหัสผ่านตรงนี้';
GRANT ALL PRIVILEGES ON ferry_booking.* TO 'ferry_booking'@'localhost';
FLUSH PRIVILEGES;
