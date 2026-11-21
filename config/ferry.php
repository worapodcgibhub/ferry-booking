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

    // เครดิตภาพปกสาธิต (ภาพจริงจาก Wikimedia Commons) แสดงท้ายหน้าเว็บเฉพาะเกาะที่ยังใช้ภาพนี้เป็นภาพปกอยู่
    // คีย์ = cover_path ในฐานข้อมูล; ไฟล์ภาพและ CREDITS.md อยู่ที่ database/seeders/demo-images/
    'cover_credits' => [
        'covers/koh-kood.jpg' => [
            'title' => 'Ko Kut, Trat.JPG',
            'author' => 'Sry85',
            'license' => 'CC BY-SA 3.0',
            'license_url' => 'https://creativecommons.org/licenses/by-sa/3.0/',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Ko_Kut,_Trat.JPG',
        ],
        'covers/koh-mak.jpg' => [
            'title' => 'Koh Mak (island), Thailand, Sunset on the beach with palms.jpg',
            'author' => 'Vyacheslav Argenberg',
            'license' => 'CC BY 4.0',
            'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Koh_Mak_(island),_Thailand,_Sunset_on_the_beach_with_palms.jpg',
        ],
    ],
];
