<?php

use App\Services\BookingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('bookings:expire', function () {
    $n = app(BookingService::class)->expireDue();
    $this->info("คืนที่นั่งจากรายการหมดเวลา {$n} รายการ");
})->purpose('คืนที่นั่งของรายการที่เลยเวลาแจ้งโอน');

Schedule::command('bookings:expire')->everyMinute();
