<?php

namespace App\Livewire;

use App\Models\Booking;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * ตรวจสอบการจองด้วยเลขอ้างอิง + (เบอร์โทรหรืออีเมลที่ใช้จอง) แล้วพาไปหน้าสถานะ
 * ไม่ตรง/ไม่มีเลขนั้น → ข้อความเดียวกันทุกกรณี และจำกัดจำนวนครั้งตาม IP และตามเลขอ้างอิง
 */
class BookingLookup extends Component
{
    public const MAX_ATTEMPTS = 5;
    public const DECAY_SECONDS = 600;

    public string $reference = '';
    public string $contact = '';
    public ?string $error = null;

    public function search()
    {
        $this->error = null;
        $this->validate([
            'reference' => 'required|string|max:40',
            'contact' => 'required|string|max:120',
        ], [
            'reference.required' => 'กรุณากรอกเลขอ้างอิงการจอง',
            'contact.required' => 'กรุณากรอกเบอร์โทรหรืออีเมลที่ใช้จอง',
        ]);

        $ref = strtoupper(trim($this->reference));
        $keys = ['lookup:ip:'.request()->ip(), 'lookup:ref:'.$ref];

        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
                $this->error = "ลองค้นหาหลายครั้งเกินไป กรุณารออีกประมาณ {$minutes} นาทีแล้วลองใหม่";

                return;
            }
        }
        // นับทุกครั้ง ทั้งถูกและผิด
        foreach ($keys as $key) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
        }

        $booking = Booking::where('reference', $ref)->first();
        if ($booking && $this->contactMatches($booking, trim($this->contact))) {
            return $this->redirectRoute('booking.show', ['token' => $booking->access_token]);
        }

        $this->error = 'ไม่พบการจองที่ตรงกับข้อมูลนี้ กรุณาตรวจเลขอ้างอิงและเบอร์โทรหรืออีเมลที่ใช้จองอีกครั้ง';
    }

    private function contactMatches(Booking $booking, string $input): bool
    {
        if (str_contains($input, '@')) {
            return mb_strtolower($input) === mb_strtolower(trim($booking->contact_email));
        }
        $given = Booking::normalizePhone($input);

        return $given !== '' && hash_equals(Booking::normalizePhone($booking->contact_phone), $given);
    }

    public function render()
    {
        return view('livewire.booking-lookup')
            ->layout('layouts::app', ['title' => 'ตรวจสอบการจอง / แจ้งโอนเงิน — เรือไปเกาะ']);
    }
}
