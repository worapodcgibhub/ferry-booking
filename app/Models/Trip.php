<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    protected $guarded = [];
    protected $casts = ['departs_at' => 'datetime', 'arrives_at' => 'datetime'];

    public function route() { return $this->belongsTo(Route::class); }

    public function getSeatsLeftAttribute(): int
    {
        return max(0, $this->capacity - $this->seats_taken);
    }

    public function isBookable(int $passengers = 1): bool
    {
        return $this->status === 'open' && $this->departs_at->isFuture() && $this->seats_left >= $passengers;
    }
}
