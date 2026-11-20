<?php

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('thai_date')) {
    function thai_date(CarbonInterface|string|null $d, bool $short = true): string
    {
        if (! $d) {
            return '-';
        }
        $d = Carbon::parse($d);
        $months = $short
            ? ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.']
            : ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

        return $d->day.' '.$months[$d->month - 1].' '.($d->year + 543);
    }
}

if (! function_exists('thai_datetime')) {
    function thai_datetime(CarbonInterface|string|null $d): string
    {
        return $d ? thai_date($d).' '.Carbon::parse($d)->format('H:i') : '-';
    }
}
