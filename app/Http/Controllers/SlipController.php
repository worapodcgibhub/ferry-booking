<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;

/** สลิปเก็บในดิสก์ส่วนตัว เปิดได้เฉพาะเจ้าหน้าที่ที่ล็อกอิน หรือเจ้าของลิงก์เฉพาะรายการ */
class SlipController extends Controller
{
    public function forStaff(Payment $payment)
    {
        return $this->stream($payment);
    }

    public function forCustomer(string $token)
    {
        $booking = Booking::where('access_token', $token)->firstOrFail();
        $payment = $booking->payments()->firstOrFail();

        return $this->stream($payment);
    }

    private function stream(Payment $payment)
    {
        abort_unless(Storage::disk('local')->exists($payment->slip_path), 404);

        return Storage::disk('local')->response($payment->slip_path, null, ['Cache-Control' => 'private, no-store']);
    }
}
