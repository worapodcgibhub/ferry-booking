<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $guarded = [];
    protected $casts = [
        'expires_at' => 'datetime', 'seats_released_at' => 'datetime', 'confirmed_at' => 'datetime',
    ];

    public const BOOKING_LABELS = [
        'pending' => 'รอดำเนินการ', 'confirmed' => 'ยืนยันแล้ว', 'cancelled' => 'ยกเลิก', 'expired' => 'หมดเวลา',
    ];
    public const PAYMENT_LABELS = [
        'unpaid' => 'ยังไม่แจ้งโอน', 'submitted' => 'รอตรวจยอด', 'verified' => 'ตรวจยอดแล้ว', 'rejected' => 'ยอดไม่ตรง/ขอหลักฐานใหม่',
    ];

    /** เบอร์โทรเทียบกันได้: ตัดทุกอย่างที่ไม่ใช่ตัวเลข และแปลง +66 / 66 นำหน้าเป็น 0 */
    public static function normalizePhone(?string $phone): string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($d, '66') && strlen($d) >= 11) {
            $d = '0'.substr($d, 2);
        }

        return $d;
    }

    public function legs() { return $this->hasMany(BookingLeg::class)->orderBy('id'); }
    public function payments() { return $this->hasMany(Payment::class)->latest('id'); }

    public function getBookingStatusLabelAttribute(): string { return self::BOOKING_LABELS[$this->booking_status] ?? $this->booking_status; }
    public function getPaymentStatusLabelAttribute(): string { return self::PAYMENT_LABELS[$this->payment_status] ?? $this->payment_status; }

    public function isOpenForPayment(): bool
    {
        return $this->booking_status === 'pending'
            && in_array($this->payment_status, ['unpaid', 'rejected'], true)
            && $this->expires_at?->isFuture();
    }
}
