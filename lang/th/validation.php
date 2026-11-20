<?php

return [
    'required' => 'กรุณากรอก :attribute',
    'uploaded' => 'อัปโหลด:attributeไม่สำเร็จ กรุณาลองใหม่อีกครั้ง',
    'file' => ':attribute ต้องเป็นไฟล์',
    'image' => ':attribute ต้องเป็นไฟล์ภาพ',
    'mimes' => ':attribute ต้องเป็นไฟล์ชนิด: :values',
    'max' => [
        'file' => ':attribute ต้องมีขนาดไม่เกิน :max กิโลไบต์',
        'string' => ':attribute ต้องมีความยาวไม่เกิน :max ตัวอักษร',
        'numeric' => ':attribute ต้องไม่เกิน :max',
    ],
    'min' => [
        'string' => ':attribute ต้องมีอย่างน้อย :min ตัวอักษร',
        'numeric' => ':attribute ต้องไม่น้อยกว่า :min',
    ],
    'email' => 'รูปแบบ:attributeไม่ถูกต้อง',
    'integer' => ':attribute ต้องเป็นจำนวนเต็ม',
    'date' => ':attribute ไม่ใช่วันที่ที่ถูกต้อง',
    'exists' => ':attribute ที่เลือกไม่ถูกต้อง',
    'unique' => ':attribute นี้ถูกใช้แล้ว',
    'different' => ':attribute ต้องไม่ซ้ำกับ :other',
    'url' => 'รูปแบบ:attributeไม่ถูกต้อง',
    'after_or_equal' => ':attribute ต้องเป็นวันที่ตั้งแต่ :date เป็นต้นไป',
    'before_or_equal' => ':attribute ต้องไม่เกิน :date',
    'attributes' => [
        'slip' => 'สลิป', 'files.0' => 'ไฟล์', 'amount' => 'ยอดที่โอน', 'transferredAt' => 'เวลาที่โอน',
        'cover' => 'รูปปก', 'name' => 'ชื่อ', 'email' => 'อีเมล', 'password' => 'รหัสผ่าน',
    ],
];
