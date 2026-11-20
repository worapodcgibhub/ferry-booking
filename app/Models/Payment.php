<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = [];
    protected $casts = ['transferred_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
