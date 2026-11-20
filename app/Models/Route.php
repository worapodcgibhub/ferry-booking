<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public function origin() { return $this->belongsTo(Port::class, 'origin_port_id'); }
    public function destination() { return $this->belongsTo(Port::class, 'destination_port_id'); }
    public function island() { return $this->belongsTo(Island::class); }
    public function trips() { return $this->hasMany(Trip::class); }

    public function getLabelAttribute(): string
    {
        return $this->origin->name.' → '.$this->destination->name;
    }

    /** เส้นทางขากลับ (สลับต้นทาง/ปลายทาง) ถ้ามี */
    public function reverse(): ?self
    {
        return static::where('origin_port_id', $this->destination_port_id)
            ->where('destination_port_id', $this->origin_port_id)->where('is_active', true)->first();
    }
}
