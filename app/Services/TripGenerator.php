<?php

namespace App\Services;

use App\Models\Route;
use App\Models\Trip;
use Carbon\CarbonInterface;

class TripGenerator
{
    /**
     * สร้างรอบเรือเป็นชุด: ทุกวันในช่วงที่เลือก (กรองตามวันในสัปดาห์ได้) × รายการเวลา
     * ข้ามรอบที่มีอยู่แล้ว (เส้นทาง+เวลาออกเดียวกัน) เพื่อไม่ให้สร้างซ้ำ
     *
     * @param  list<string>  $times  เช่น ['08:00', '11:00']
     * @param  list<int>  $weekdays  0=อาทิตย์ ... 6=เสาร์ (ว่าง = ทุกวัน)
     * @return int จำนวนรอบที่สร้างใหม่
     */
    public function generate(Route $route, CarbonInterface $from, CarbonInterface $to, array $times, int $price, int $capacity, array $weekdays = [], string $provider = 'เรือไปเกาะ'): int
    {
        $created = 0;
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            if ($weekdays && ! in_array($day->dayOfWeek, $weekdays, true)) {
                continue;
            }
            foreach ($times as $time) {
                [$h, $m] = array_map('intval', explode(':', $time));
                $departs = $day->copy()->setTime($h, $m);
                if (Trip::where('route_id', $route->id)->where('departs_at', $departs)->exists()) {
                    continue;
                }
                Trip::create([
                    'route_id' => $route->id,
                    'departs_at' => $departs,
                    'arrives_at' => $departs->copy()->addMinutes($route->duration_minutes),
                    'price' => $price,
                    'capacity' => $capacity,
                    'provider' => $provider,
                ]);
                $created++;
            }
        }

        return $created;
    }
}
