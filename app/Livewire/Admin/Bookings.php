<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Bookings extends Component
{
    use WithPagination;

    #[Url] public string $q = '';
    #[Url] public string $filter = 'all'; // all | review | pending | confirmed | closed

    public function updatingQ(): void { $this->resetPage(); }
    public function updatingFilter(): void { $this->resetPage(); }

    public function render()
    {
        $bookings = Booking::query()
            ->when(trim($this->q) !== '', function ($q) {
                $s = '%'.trim($this->q).'%';
                $q->where(fn ($w) => $w->where('reference', 'like', $s)->orWhere('contact_name', 'like', $s)
                    ->orWhere('contact_phone', 'like', $s)->orWhere('contact_email', 'like', $s));
            })
            ->when($this->filter === 'review', fn ($q) => $q->where('booking_status', 'pending')->where('payment_status', 'submitted'))
            ->when($this->filter === 'pending', fn ($q) => $q->where('booking_status', 'pending'))
            ->when($this->filter === 'confirmed', fn ($q) => $q->where('booking_status', 'confirmed'))
            ->when($this->filter === 'closed', fn ($q) => $q->whereIn('booking_status', ['cancelled', 'expired']))
            ->latest('id')->paginate(15);

        return view('livewire.admin.bookings', compact('bookings'))->layout('layouts::admin', ['title' => 'รายการจอง']);
    }
}
