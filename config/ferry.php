<?php

return [
    // เวลาที่ลูกค้าต้องแจ้งโอนหลังส่งคำขอจอง (นาที)
    'hold_minutes' => (int) env('FERRY_HOLD_MINUTES', 60),
    // เวลาให้ส่งหลักฐานใหม่เมื่อทีมงานแจ้งว่ายอดไม่ตรง (นาที)
    'evidence_minutes' => (int) env('FERRY_EVIDENCE_MINUTES', 1440),
    'max_passengers' => 10,
    // ข้อมูลสมมติ ใช้ทดสอบเท่านั้น
    'bank' => [
        'name' => 'ธนาคารตัวอย่าง (ข้อมูลทดสอบ)',
        'account_name' => 'บจก. เรือไปเกาะ (ทดสอบ)',
        'account_no' => '000-0-00000-0',
    ],
    'slip_max_kb' => 4096,
];
