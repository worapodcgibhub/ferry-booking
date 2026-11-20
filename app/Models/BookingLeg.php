<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingLeg extends Model
{
    protected $guarded = [];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function trip() { return $this->belongsTo(Trip::class); }
}
