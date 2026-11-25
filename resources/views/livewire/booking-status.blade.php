<div class="mx-auto max-w-2xl px-4 py-8" @if ($booking->booking_status === 'pending') wire:poll.15s @endif>
    <div class="card p-6">
        <div class="text-center">
            <h1 class="text-3xl font-extrabold">
                @switch($booking->booking_status)
                    @case('confirmed') ยืนยันการจองแล้ว @break
                    @case('cancelled') การจองถูกยกเลิก @break
                    @case('expired') หมดเวลาแจ้งโอน @break
                    @default ส่งคำขอจองแล้ว
                @endswitch
            </h1>
            <p class="mt-2 text-slate-600">เลขจอง <span class="text-2xl font-extrabold text-navy">{{ $booking->reference }}</span></p>
            <div class="mt-3 flex flex-wrap justify-center gap-2 text-sm">
                <span class="badge {{ ['pending' => 'bg-amber-100 text-amber-800', 'confirmed' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-slate-200 text-slate-700', 'expired' => 'bg-slate-200 text-slate-700'][$booking->booking_status] }}">สถานะการจอง: {{ $booking->booking_status_label }}</span>
                <span class="badge {{ ['unpaid' => 'bg-slate-200 text-slate-700', 'submitted' => 'bg-sky-100 text-sky-800', 'verified' => 'bg-green-100 text-green-800', 'rejected' => 'bg-pink-50 text-pink'][$booking->payment_status] }}">การชำระเงิน: {{ $booking->payment_status_label }}</span>
            </div>
        </div>

        <div class="mt-5 rounded-control bg-sea-50 p-4">
            <h2 class="mb-2 font-extrabold">สรุปคำขอจอง ({{ $booking->trip_type === 'round' ? 'ไป–กลับ' : 'เที่ยวเดียว' }})</h2>
            @foreach ($booking->legs as $leg)
                <div class="border-b py-2 text-sm last:border-0">
                    <b>{{ $leg->direction === 'return' ? 'ขากลับ' : 'ขาไป' }}:</b> {{ $leg->trip->route->label }}<br>
                    {{ thai_date($leg->trip->departs_at) }} · ออก {{ $leg->trip->departs_at->format('H:i') }} น. ถึง {{ $leg->trip->arrives_at->format('H:i') }} น.<br>
                    ท่าขึ้นเรือ: {{ $leg->trip->route->origin->name }} · {{ number_format($leg->unit_price) }} × {{ $leg->passengers }} = {{ number_format($leg->amount) }} บาท
                </div>
            @endforeach
            <div class="mt-2 flex justify-between font-extrabold"><span>{{ $booking->passengers }} คน · ยอดรวม</span><span class="text-pink">{{ number_format($booking->total_amount) }} บาท</span></div>
        </div>

        @if ($booking->booking_status === 'confirmed')
            <p class="mt-4 rounded-control bg-green-50 p-4 font-semibold text-green-800">ทีมงานตรวจยอดและยืนยันแล้ว เราส่งอีเมลยืนยันพร้อมเลขจองไปที่ {{ $booking->contact_email }} แล้ว กรุณามาถึงท่าก่อนเรือออกอย่างน้อย 30 นาที</p>
        @elseif (in_array($booking->booking_status, ['cancelled', 'expired']))
            <div class="mt-4 rounded-control bg-slate-100 p-4">
                @if ($booking->booking_status === 'expired')
                    <p class="font-semibold">การจองนี้หมดเวลาแจ้งโอน{{ $booking->expires_at ? 'เมื่อ '.thai_datetime($booking->expires_at).' น.' : '' }} ระบบคืนที่นั่งแล้ว ลิงก์นี้แจ้งโอนไม่ได้อีก หากยังต้องการเดินทางกรุณาจองใหม่</p>
                @else
                    <p class="font-semibold">การจองนี้ถูกยกเลิกและคืนที่นั่งแล้ว</p>
                @endif
                <a href="{{ route('home') }}" class="btn-pink mt-3 w-full py-3 text-lg">จองใหม่ ›</a>
            </div>
        @elseif ($booking->payment_status === 'submitted')
            <p class="mt-4 rounded-control bg-sky-50 p-4 font-semibold text-sky-900">เรารับสลิปแล้ว ขณะนี้รอทีมงานตรวจยอดเงินเข้าบัญชี เมื่อยืนยันแล้วจะส่งเลขจองทางอีเมล {{ $booking->contact_email }}</p>
        @else
            {{-- ข้อมูลโอน + แจ้งโอน --}}
            <div class="mt-4 rounded-control border-2 border-pink p-4">
                <h2 class="font-extrabold">โอนเงินตามยอดนี้</h2>
                <div class="mt-1 text-3xl font-extrabold text-pink">{{ number_format($booking->total_amount) }} บาท</div>
                <dl class="mt-2 text-sm">
                    <div><dt class="inline text-slate-500">ธนาคาร:</dt> <dd class="inline font-bold">{{ $bank['name'] }}</dd></div>
                    <div><dt class="inline text-slate-500">ชื่อบัญชี:</dt> <dd class="inline font-bold">{{ $bank['account_name'] }}</dd></div>
                    <div><dt class="inline text-slate-500">เลขบัญชี:</dt> <dd class="inline text-lg font-extrabold">{{ $bank['account_no'] }}</dd></div>
                </dl>
                <p class="mt-2 text-sm font-semibold" x-data="{ exp: {{ (int) ($booking->expires_at?->timestamp ?? 0) }}, left: 0 }"
                   x-init="left = Math.max(0, exp - Math.floor(Date.now()/1000)); setInterval(() => left = Math.max(0, exp - Math.floor(Date.now()/1000)), 1000)">
                    กรุณาแจ้งโอนก่อน {{ thai_datetime($booking->expires_at) }} น.
                    (เหลือ <span x-text="Math.floor(left/60) + ' นาที ' + (left%60) + ' วินาที'"></span>) มิฉะนั้นระบบจะคืนที่นั่ง
                </p>
                <p class="text-xs text-amber-700">ข้อมูลบัญชีนี้เป็นข้อมูลทดสอบในเครื่องเท่านั้น</p>
            </div>

            <div class="mt-4 rounded-control bg-sea-50 p-4 text-sm">
                <p class="font-semibold">ถ้าโอนทีหลัง ให้กลับมาที่หน้านี้ภายใน {{ thai_datetime($booking->expires_at) }} น.</p>
                <p class="mt-1 text-slate-600">เก็บลิงก์นี้ไว้ หรือกลับมาได้ทุกเมื่อที่ <a href="{{ route('booking.lookup') }}" class="font-bold text-pink underline">ตรวจสอบการจอง / แจ้งโอนเงิน</a> ด้วยเลขอ้างอิง {{ $booking->reference }} และเบอร์โทรหรืออีเมลที่ใช้จอง</p>
                <x-copy-box class="mt-2" :text="route('booking.show', $booking->access_token)" label="คัดลอกลิงก์หน้านี้" btn-class="btn-line" />
            </div>

            @if ($booking->payment_status === 'rejected')
                @php($last = $booking->payments->first())
                <div class="mt-4 rounded-control border border-pink bg-pink-50 p-4 text-sm">
                    <b class="text-pink">ทีมงานแจ้งว่ายอดไม่ตรง / ขอหลักฐานใหม่</b>
                    @if ($last?->review_note)<p class="mt-1">เหตุผล: {{ $last->review_note }}</p>@endif
                    <p class="mt-1">กรุณาแจ้งโอนพร้อมแนบสลิปใหม่</p>
                </div>
            @endif

            @if ($booking->isOpenForPayment())
                <form wire:submit="submitPayment" class="mt-4 space-y-3">
                    <h2 class="font-extrabold">แจ้งโอนและแนบสลิป</h2>
                    @if ($message)<div class="rounded-control bg-pink-50 p-3 font-semibold text-pink">{{ $message }}</div>@endif
                    <div>
                        <label class="label" for="amt">ยอดที่โอน (บาท)</label>
                        <input id="amt" type="number" wire:model="amount" class="field" min="1">
                        @error('amount')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="at">วันและเวลาที่โอน</label>
                        <input id="at" type="datetime-local" wire:model="transferredAt" class="field">
                        @error('transferredAt')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="slip">สลิปการโอน (JPG, PNG, PDF ไม่เกิน {{ config('ferry.slip_max_kb') / 1024 }} MB)</label>
                        <input id="slip" type="file" wire:model="slip" accept=".jpg,.jpeg,.png,.pdf" class="field">
                        <div wire:loading wire:target="slip" class="text-sm text-slate-500">กำลังอัปโหลด…</div>
                        @error('slip')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    <button class="btn-pink w-full py-3.5 text-lg" wire:loading.attr="disabled" wire:target="submitPayment,slip">แจ้งโอน ›</button>
                </form>
            @endif
        @endif

        <div class="mt-6 text-center"><a href="{{ route('home') }}" class="btn-line">กลับหน้าแรก</a></div>
        <p class="mt-3 text-center text-xs text-slate-500">เก็บลิงก์หน้านี้ไว้เพื่อติดตามสถานะ ลิงก์นี้เป็นส่วนตัว อย่าแชร์ให้ผู้อื่น</p>
    </div>
</div>
