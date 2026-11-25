<div style="font-family:Tahoma,sans-serif;color:#0b1f4d">
    <h2>ยืนยันการจองเรือเรียบร้อยแล้ว</h2>
    <p>เรียน คุณ{{ $booking->contact_name }}</p>
    <p>เลขจองของคุณ: <strong style="font-size:20px">{{ $booking->reference }}</strong></p>
    @foreach ($booking->legs as $leg)
        <p>
            <strong>{{ $leg->direction === 'return' ? 'ขากลับ' : 'ขาไป' }}</strong>:
            {{ $leg->trip->route->label }}<br>
            ออกเดินทาง {{ thai_datetime($leg->trip->departs_at) }} น.<br>
            ท่าขึ้นเรือ: {{ $leg->trip->route->origin->name }}
            @if ($leg->trip->route->origin->boarding_point) ({{ $leg->trip->route->origin->boarding_point }}) @endif
        </p>
    @endforeach
    <p>จำนวนผู้โดยสาร: {{ $booking->passengers }} คน · ยอดรวม {{ number_format($booking->total_amount) }} บาท</p>
    <p>ดูสถานะการจอง: <a href="{{ route('booking.show', $booking->access_token) }}">{{ route('booking.show', $booking->access_token) }}</a> (ลิงก์ส่วนตัว อย่าแชร์ให้ผู้อื่น)</p>
    <p>กรุณามาถึงท่าก่อนเวลาเรือออกอย่างน้อย 30 นาที และแสดงเลขจองต่อเจ้าหน้าที่</p>
</div>
