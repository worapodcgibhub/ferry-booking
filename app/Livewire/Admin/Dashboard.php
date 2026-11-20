<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\BookingLeg;
use App\Models\Trip;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $today = now()->toDateString();
        $trips = Trip::with('route.origin', 'route.destination')->whereDate('departs_at', $today)->orderBy('departs_at')->get();

        // ผู้โดยสารที่ยืนยันแล้วของรอบวันนี้
        $confirmed = BookingLeg::whereIn('trip_id', $trips->pluck('id'))
            ->whereHas('booking', fn ($q) => $q->where('booking_status', 'confirmed'))
            ->selectRaw('trip_id, SUM(passengers) as pax')->groupBy('trip_id')->pluck('pax', 'trip_id');

        return view('livewire.admin.dashboard', [
            'waiting' => Booking::where('booking_status', 'pending')->where('payment_status', 'submitted')->count(),
            'unpaid' => Booking::where('booking_status', 'pending')->whereIn('payment_status', ['unpaid', 'rejected'])->count(),
            'confirmedToday' => $confirmed->sum(),
            'trips' => $trips,
            'confirmed' => $confirmed,
        ])->layout('layouts::admin', ['title' => 'ภาพรวมวันนี้']);
    }
}
