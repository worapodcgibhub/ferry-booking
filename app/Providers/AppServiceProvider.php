<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // `php artisan serve` ส่งต่อตัวแปรสภาพแวดล้อมให้โปรเซสเซิร์ฟเวอร์เฉพาะรายการที่กำหนด ซึ่งไม่มี TEMP/TMP
        // บน Windows PHP จึงตกไปใช้ C:\Windows (เขียนไม่ได้) และรับไฟล์อัปโหลดไม่ได้ (สลิปขึ้น "failed to upload")
        if (class_exists(ServeCommand::class)) {
            ServeCommand::$passthroughVariables = array_values(array_unique(array_merge(
                ServeCommand::$passthroughVariables,
                ['TEMP', 'TMP', 'TMPDIR', 'PHP_INI_SCAN_DIR'],
            )));

            // php.ini ของ PHP ตัวนี้ตั้ง upload_max_filesize=2M ต่ำกว่าเพดานสลิปของแอป (4 MB)
            // ให้เซิร์ฟเวอร์ที่เปิดด้วย `artisan serve` โหลด php-ini/ferry.ini เพิ่ม (เซิร์ฟเวอร์จริงตั้งค่าเองตาม README)
            if (($_SERVER['argv'][1] ?? null) === 'serve' && is_dir(base_path('php-ini'))) {
                $_ENV['PHP_INI_SCAN_DIR'] = base_path('php-ini');
            }
        }
    }
}
