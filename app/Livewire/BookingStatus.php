<?php

namespace App\Livewire;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Services\BookingService;
use Carbon\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class BookingStatus extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $token = '';

    public ?int $amount = null;
    public ?string $transferredAt = null;
    public $slip = null;
    public ?string $message = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $booking = $this->booking();
        $this->amount = $booking->total_amount;
        $this->transferredAt = now()->format('Y-m-d\TH:i');
    }

    /** เข้าถึงด้วยลิงก์เฉพาะรายการ (token ยาวสุ่ม) เท่านั้น เลขจองอย่างเดียวเปิดไม่ได้ */
    private function booking(): Booking
    {
        return Booking::with(['legs.trip.route.origin', 'legs.trip.route.destination', 'payments'])
            ->where('access_token', $this->token)->firstOrFail();
    }

    public function submitPayment(): void
    {
        $booking = $this->booking();

        $this->validate([
            'amount' => 'required|integer|min:1',
            'transferredAt' => 'required|date|before_or_equal:now',
            'slip' => 'required|file|mimes:jpg,jpeg,png,pdf|max:'.config('ferry.slip_max_kb'),
        ], [
            'amount.required' => 'กรุณากรอกยอดที่โอน',
            'transferredAt.required' => 'กรุณาระบุวันและเวลาที่โอน',
            'transferredAt.before_or_equal' => 'เวลาที่โอนต้องไม่เป็นอนาคต',
            'slip.required' => 'กรุณาแนบสลิปการโอน',
            'slip.mimes' => 'ไฟล์สลิปต้องเป็น JPG, PNG หรือ PDF',
            'slip.max' => 'ไฟล์สลิปต้องไม่เกิน '.(config('ferry.slip_max_kb') / 1024).' MB',
        ]);

        try {
            app(BookingService::class)->submitPayment($booking, (int) $this->amount, Carbon::parse($this->transferredAt), $this->slip);
        } catch (BookingException $e) {
            $this->message = $e->getMessage();

            return;
        }

        $this->reset('slip');
        $this->message = null;
    }

    public function render()
    {
        app(BookingService::class)->expireDue();

        return view('livewire.booking-status', ['booking' => $this->booking(), 'bank' => config('ferry.bank')])
            ->layout('layouts::app', ['title' => 'สถานะการจอง — เรือไปเกาะ']);
    }
}
