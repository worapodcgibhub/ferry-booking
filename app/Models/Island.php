<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Island extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public function area() { return $this->belongsTo(Area::class); }
    public function ports() { return $this->hasMany(Port::class); }
    public function routes() { return $this->hasMany(Route::class); }

    public function getCoverUrlAttribute(): string
    {
        if (! $this->cover_path) {
            return asset('images/covers/default.svg');
        }

        // ภาพสาธิตที่มากับระบบอยู่ใน public/images ส่วนภาพที่แอดมินอัปโหลดอยู่ใน storage
        // ใช้ asset() (โฮสต์ของคำขอปัจจุบัน) ไม่ใช้ Storage::url() ที่อิง APP_URL ซึ่งถ้าเปิดเว็บจาก
        // 127.0.0.1 หรือ IP ในวง LAN/มือถือ รูปปกที่อัปโหลดจะชี้ไป localhost แล้วโหลดไม่ขึ้น
        return asset(str_starts_with($this->cover_path, 'images/') ? $this->cover_path : 'storage/'.$this->cover_path);
    }

    public function getHeadlineTextAttribute(): string
    {
        return $this->headline ?: 'ไป'.$this->name;
    }
}
