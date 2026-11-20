<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $guarded = [];

    public function islands() { return $this->hasMany(Island::class); }
    public function ports() { return $this->hasMany(Port::class); }
}
