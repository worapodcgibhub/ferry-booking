<?php

namespace App\Livewire\Admin;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Services\BookingService;
use Livewire\Attributes\Locked;
use Livewire\Component;

class BookingDetail extends Component
{
    #[Locked]
    public int $bookingId;

    public string $reason = '';
    public ?string $flash = null;
    public ?string $error = null;

    public function mount(Booking $booking): void
    {
        $this->bookingId = $booking->id;
    }

    private function booking(): Booking
    {
        return Booking::with(['legs.trip.route.origin', 'legs.trip.route.destination', 'payments.reviewer'])->findOrFail($this->bookingId);
    }

    private function run(callable $fn, string $ok): void
    {
        $this->error = $this->flash = null;
        try {
            $fn(app(BookingService::class), $this->booking());
            $this->flash = $ok;
            $this->reason = '';
        } catch (BookingException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function confirm(): void
    {
        $this->run(fn ($s, $b) => $s->confirm($b, auth()->user()), 'ยืนยันการจองแล้ว และบันทึกอีเมลยืนยันลง log (โหมดทดสอบ)');
    }

    public function requestEvidence(): void
    {
        $this->run(fn ($s, $b) => $s->requestNewEvidence($b, auth()->user(), $this->reason), 'บันทึกเหตุผลและขอหลักฐานใหม่จากลูกค้าแล้ว');
    }

    public function cancelBooking(): void
    {
        $this->run(fn ($s, $b) => $s->cancel($b, auth()->user(), $this->reason ?: null), 'ยกเลิกการจองและคืนที่นั่งแล้ว');
    }

    public function render()
    {
        $b = $this->booking();

        return view('livewire.admin.booking-detail', [
            'b' => $b,
            'line' => $b->booking_status === 'confirmed' ? app(BookingService::class)->lineMessage($b) : null,
        ])->layout('layouts::admin', ['title' => 'การจอง '.$b->reference]);
    }
}
