<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SlipController;
use App\Livewire\Admin;
use App\Livewire\BookingFlow;
use App\Livewire\BookingLookup;
use App\Livewire\BookingStatus;
use App\Livewire\Home;
use Illuminate\Support\Facades\Route;

// ---- ลูกค้า (ไม่ต้องสมัครสมาชิก) ----
Route::get('/', Home::class)->name('home');
Route::get('/book', BookingFlow::class)->name('book');
Route::get('/booking-lookup', BookingLookup::class)->name('booking.lookup');
Route::get('/booking/{token}', BookingStatus::class)->name('booking.show')->where('token', '[A-Za-z0-9]{48}');
Route::get('/booking/{token}/slip', [SlipController::class, 'forCustomer'])->name('booking.slip')->where('token', '[A-Za-z0-9]{48}');

// ---- เจ้าหน้าที่ ----
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt')->middleware('throttle:10,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::prefix('admin')->middleware('auth')->group(function () {
    Route::get('/', Admin\Dashboard::class)->name('admin.dashboard');
    Route::get('/bookings', Admin\Bookings::class)->name('admin.bookings');
    Route::get('/bookings/{booking}', Admin\BookingDetail::class)->name('admin.bookings.show');
    Route::get('/passengers', Admin\Passengers::class)->name('admin.passengers');
    Route::get('/slips/{payment}', [SlipController::class, 'forStaff'])->name('admin.slip');

    Route::middleware('owner')->group(function () {
        Route::get('/islands', Admin\Islands::class)->name('admin.islands');
        Route::get('/ports', Admin\Ports::class)->name('admin.ports');
        Route::get('/routes', Admin\Routes::class)->name('admin.routes');
        Route::get('/trips', Admin\Trips::class)->name('admin.trips');
        Route::get('/users', Admin\Users::class)->name('admin.users');
    });
});
