<div>
    <a href="{{ route('admin.bookings') }}" class="text-sm font-semibold text-pink">‹ รายการจอง</a>
    <div class="mt-2 mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-extrabold">{{ $b->reference }}</h1>
        <span class="badge {{ ['pending' => 'bg-amber-100 text-amber-800', 'confirmed' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-slate-200', 'expired' => 'bg-slate-200'][$b->booking_status] }}">สถานะการจอง: {{ $b->booking_status_label }}</span>
        <span class="badge {{ ['unpaid' => 'bg-slate-200', 'submitted' => 'bg-sky-100 text-sky-800', 'verified' => 'bg-green-100 text-green-800', 'rejected' => 'bg-pink-50 text-pink'][$b->payment_status] }}">การชำระเงิน: {{ $b->payment_status_label }}</span>
    </div>

    @if ($flash)<div class="mb-3 rounded-control bg-green-50 p-3 font-semibold text-green-800">{{ $flash }}</div>@endif
    @if ($error)<div class="mb-3 rounded-control bg-pink-50 p-3 font-semibold text-pink">{{ $error }}</div>@endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="card p-4">
            <h2 class="mb-2 font-extrabold">ผู้จองและรอบเรือ</h2>
            <p>{{ $b->contact_name }} · {{ $b->contact_phone }} · {{ $b->contact_email }}</p>
            <p class="text-sm text-slate-500">จองเมื่อ {{ thai_datetime($b->created_at) }}@if($b->expires_at && $b->booking_status === 'pending') · กำหนดแจ้งโอน {{ thai_datetime($b->expires_at) }}@endif</p>
            @foreach ($b->legs as $leg)
                <div class="mt-3 rounded-control bg-sea-50 p-3 text-sm">
                    <b>{{ $leg->direction === 'return' ? 'ขากลับ' : 'ขาไป' }}:</b> {{ $leg->trip->route->label }}<br>
                    {{ thai_date($leg->trip->departs_at) }} ออก {{ $leg->trip->departs_at->format('H:i') }} น. · ท่าขึ้นเรือ {{ $leg->trip->route->origin->name }}<br>
                    {{ number_format($leg->unit_price) }} × {{ $leg->passengers }} คน = <b>{{ number_format($leg->amount) }} บาท</b>
                </div>
            @endforeach
            <p class="mt-3 text-lg font-extrabold">ยอดรวม <span class="text-pink">{{ number_format($b->total_amount) }} บาท</span> ({{ $b->passengers }} คน)</p>
            @if ($b->staff_note)<p class="mt-2 text-sm">หมายเหตุทีมงาน: {{ $b->staff_note }}</p>@endif
        </div>

        <div class="card p-4">
            <h2 class="mb-2 font-extrabold">การแจ้งโอนและสลิป</h2>
            @forelse ($b->payments as $p)
                <div class="mb-3 rounded-control border p-3 text-sm" wire:key="pay-{{ $p->id }}">
                    <div>แจ้งยอด <b>{{ number_format($p->reported_amount) }} บาท</b> {{ $p->reported_amount === $b->total_amount ? '(ตรงกับยอดจอง)' : '(ไม่ตรงกับยอดจอง '.number_format($b->total_amount).')' }}</div>
                    <div>เวลาโอน {{ thai_datetime($p->transferred_at) }} · แจ้งเมื่อ {{ thai_datetime($p->created_at) }}</div>
                    <div>สถานะ: <b>{{ ['submitted' => 'รอตรวจยอด', 'verified' => 'ตรวจแล้ว', 'rejected' => 'ขอหลักฐานใหม่'][$p->status] }}</b>@if($p->reviewer) โดย {{ $p->reviewer->name }}@endif</div>
                    @if ($p->review_note)<div class="text-pink">เหตุผล: {{ $p->review_note }}</div>@endif
                    <a target="_blank" href="{{ route('admin.slip', $p) }}" class="btn-line mt-2 !py-1.5 text-sm">เปิดสลิป</a>
                </div>
            @empty
                <p class="text-slate-500">ลูกค้ายังไม่แจ้งโอน</p>
            @endforelse

            @if ($b->booking_status === 'pending' && $b->payment_status === 'submitted')
                <div class="mt-3 space-y-2 border-t pt-3">
                    <p class="text-sm font-semibold">ตรวจว่าเงินเข้าบัญชีตรงยอดแล้ว จึงกดยืนยัน</p>
                    <button wire:click="confirm" wire:confirm="ยืนยันว่าตรวจเงินเข้าบัญชีแล้ว?" class="btn-pink w-full" wire:loading.attr="disabled">ยืนยันการจอง</button>
                    <label class="label" for="evidence-reason">เหตุผลที่ยอดไม่ตรง (ลูกค้าจะเห็น) <span class="text-pink">*</span></label>
                    <textarea id="evidence-reason" wire:model="evidenceReason" rows="2" class="field" placeholder="เช่น โอนมา 100 บาท ยอดจริง 900 บาท กรุณาแนบสลิปใหม่"></textarea>
                    <button wire:click="requestEvidence" class="btn-line w-full">ยอดไม่ตรง · ขอหลักฐานใหม่</button>
                </div>
            @endif
        </div>
    </div>

    @if ($line)
        {{-- navigator.clipboard ใช้ได้เฉพาะ HTTPS/localhost (http://xxx.test ไม่ใช่) จึงมีทางสำรองด้วย execCommand --}}
        <div class="card mt-4 p-4" x-data="{
            copied: false,
            failed: false,
            async copy() {
                const t = this.$refs.t;
                this.failed = false;
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(t.value);
                    } else {
                        t.focus(); t.select(); t.setSelectionRange(0, t.value.length);
                        if (!document.execCommand('copy')) throw new Error('copy failed');
                        t.setSelectionRange(0, 0); t.blur();
                    }
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                } catch (e) {
                    t.focus(); t.select();
                    this.failed = true;
                }
            }
        }">
            <h2 class="mb-2 font-extrabold">ข้อความสำหรับส่ง LINE (เจ้าหน้าที่ส่งเอง)</h2>
            <textarea x-ref="t" readonly rows="8" class="field font-mono text-sm">{{ $line }}</textarea>
            <button type="button" class="btn-pink mt-2" @click="copy()">
                <span x-show="!copied">คัดลอกข้อความ</span><span x-show="copied" x-cloak>คัดลอกแล้ว ✓</span>
            </button>
            <p x-show="failed" x-cloak class="err">คัดลอกอัตโนมัติไม่ได้ ข้อความถูกเลือกไว้แล้ว กด Ctrl+C เพื่อคัดลอกเอง</p>
        </div>
    @endif

    @if (in_array($b->booking_status, ['pending', 'confirmed']))
        <div class="card mt-4 p-4">
            <h2 class="mb-2 font-extrabold">ยกเลิกการจอง</h2>
            <p class="mb-2 text-sm text-slate-500">ยกเลิกแล้วระบบจะคืนที่นั่งครบทุกขา (ครั้งเดียว)</p>
            <label class="label" for="cancel-reason">เหตุผลการยกเลิก (บันทึกภายใน ลูกค้าไม่เห็น)</label>
            <input id="cancel-reason" wire:model="cancelReason" class="field mb-2" placeholder="เช่น ลูกค้าขอยกเลิกทางโทรศัพท์">
            <button wire:click="cancelBooking" wire:confirm="ยืนยันยกเลิกการจองนี้?" class="btn-line !border-pink !text-pink">ยกเลิกการจอง</button>
        </div>
    @endif
</div>
