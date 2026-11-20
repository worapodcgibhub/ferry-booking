<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Port extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public function area() { return $this->belongsTo(Area::class); }
    public function island() { return $this->belongsTo(Island::class); }
}
