<div style="font-family:Tahoma,sans-serif;color:#0b1f4d">
    <h2>รับคำขอจองเรือแล้ว</h2>
    <p>เรียน คุณ{{ $booking->contact_name }}</p>
    <p>เลขอ้างอิงการจอง: <strong style="font-size:20px">{{ $booking->reference }}</strong></p>
    @foreach ($booking->legs as $leg)
        <p>
            <strong>{{ $leg->direction === 'return' ? 'ขากลับ' : 'ขาไป' }}</strong>: {{ $leg->trip->route->label }}<br>
            ออกเดินทาง {{ thai_datetime($leg->trip->departs_at) }} น.
        </p>
    @endforeach
    <p>จำนวนผู้โดยสาร: {{ $booking->passengers }} คน · ยอดที่ต้องโอน <strong>{{ number_format($booking->total_amount) }} บาท</strong></p>
    <p style="padding:12px;background:#fff4e5;border:1px solid #f5b041">
        <strong>กรุณาแจ้งโอนและแนบสลิปก่อน {{ thai_datetime($booking->expires_at) }} น.</strong>
        หลังจากนั้นระบบจะคืนที่นั่งและต้องจองใหม่<br>
        ถ้าโอนทีหลัง ให้กลับมาที่ลิงก์นี้ภายใน {{ thai_datetime($booking->expires_at) }} น.:<br>
        <a href="{{ route('booking.show', $booking->access_token) }}">{{ route('booking.show', $booking->access_token) }}</a>
    </p>
    <p style="font-size:12px;color:#64748b">ลิงก์นี้เป็นส่วนตัว อย่าแชร์ให้ผู้อื่น หากลิงก์หาย สามารถค้นหาได้ที่
        <a href="{{ route('booking.lookup') }}">{{ route('booking.lookup') }}</a> ด้วยเลขอ้างอิงและเบอร์โทรหรืออีเมลที่ใช้จอง</p>
</div>
