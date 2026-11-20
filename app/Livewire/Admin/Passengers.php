<?php

namespace App\Livewire\Admin;

use App\Models\BookingLeg;
use App\Models\Trip;
use Livewire\Attributes\Url;
use Livewire\Component;

class Passengers extends Component
{
    #[Url] public ?string $date = null;
    #[Url] public ?int $tripId = null;

    public function mount(): void
    {
        $this->date ??= now()->toDateString();
    }

    public function updatedDate(): void
    {
        $this->tripId = null;
    }

    public function render()
    {
        $trips = Trip::with('route.origin', 'route.destination')->whereDate('departs_at', $this->date)->orderBy('departs_at')->get();
        $trip = $trips->firstWhere('id', $this->tripId) ?? $trips->first();

        $legs = $trip
            ? BookingLeg::with('booking')->where('trip_id', $trip->id)
                ->whereHas('booking', fn ($q) => $q->whereIn('booking_status', ['confirmed', 'pending']))->get()
                ->sortBy(fn ($l) => $l->booking->booking_status === 'confirmed' ? 0 : 1)
            : collect();

        return view('livewire.admin.passengers', [
            'trips' => $trips, 'trip' => $trip, 'legs' => $legs,
            'confirmedPax' => $legs->where('booking.booking_status', 'confirmed')->sum('passengers'),
        ])->layout('layouts::admin', ['title' => 'ผู้โดยสารตามรอบ']);
    }
}
