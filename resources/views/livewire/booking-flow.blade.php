<div>
    @php
        $isRound = $type === 'round' && $this->ret;
        $steps = $isRound
            ? ['outbound' => '2. เลือกรอบเรือขาไป', 'return' => '3. เลือกรอบเรือขากลับ', 'details' => '4. กรอกข้อมูลและส่งคำขอ']
            : ['outbound' => '2. เลือกรอบเรือขาไป', 'details' => '3. กรอกข้อมูลและส่งคำขอ'];
        $keys = array_keys($steps);
        $currentIdx = array_search($step, $keys);
    @endphp

    {{-- ขั้นตอน --}}
    <div class="border-b bg-white">
        <ol class="mx-auto flex max-w-6xl items-center gap-2 overflow-x-auto px-4 py-3 text-sm font-bold">
            <li class="flex items-center gap-2 text-sea"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-sea text-white">✓</span><span class="hidden sm:inline">1. ค้นหา</span></li>
            @foreach ($steps as $k => $label)
                @php($i = array_search($k, $keys))
                <li class="flex items-center gap-2 whitespace-nowrap {{ $i <= $currentIdx ? 'text-navy' : 'text-slate-400' }}">
                    <span class="h-0.5 w-3 shrink-0 sm:w-6 {{ $i <= $currentIdx ? 'bg-pink' : 'bg-slate-300' }}"></span>
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $i < $currentIdx ? 'bg-sea text-white' : ($i === $currentIdx ? 'bg-pink text-white' : 'bg-slate-200') }}">{{ $i < $currentIdx ? '✓' : $i + 2 }}</span>
                    {{-- มือถือแสดงชื่อเฉพาะขั้นปัจจุบัน เพื่อไม่ให้แถบขั้นตอนล้นจอ --}}
                    <span class="{{ $i === $currentIdx ? '' : 'hidden sm:inline' }}">{{ $label }}</span>
                </li>
            @endforeach
        </ol>
    </div>

    <div class="mx-auto max-w-3xl px-4 py-6">
        {{-- สรุปการค้นหา --}}
        <div class="card mb-5 grid grid-cols-3 gap-3 bg-sea-50 p-4 text-sm">
            <div><div class="text-slate-500">เส้นทาง</div><div class="font-extrabold">{{ $route?->origin->name }} → {{ $route?->destination->name }}</div></div>
            <div><div class="text-slate-500">วันที่เดินทาง</div><div class="font-extrabold">{{ thai_date($date) }}@if($isRound) – {{ thai_date($this->ret) }}@endif</div></div>
            <div><div class="text-slate-500">ผู้เดินทาง</div><div class="font-extrabold">{{ $pax }} คน</div></div>
        </div>

        @if ($notice)
            <div class="mb-4 rounded-control border border-pink bg-pink-50 p-3 font-semibold text-pink" role="alert">{{ $notice }}</div>
        @endif

        @if ($step === 'outbound')
            <h1 class="text-2xl font-extrabold">เลือกรอบเรือขาไป</h1>
            <p class="mb-3 text-slate-600">{{ $route?->label }} (ขาไป) · {{ thai_date($date, false) }}</p>
            @forelse ($outTrips as $t)
                @include('livewire.partials.trip-card', ['t' => $t, 'action' => 'chooseOutbound', 'route' => $route])
            @empty
                <p class="card p-6 text-slate-500">ไม่มีรอบเรือที่เปิดจองในวันนี้ <a class="font-bold text-pink underline" href="{{ route('home') }}">เปลี่ยนวันที่</a></p>
            @endforelse
        @elseif ($step === 'return')
            <h1 class="text-2xl font-extrabold">เลือกรอบเรือขากลับ</h1>
            <p class="mb-3 text-slate-600">{{ $route?->destination->name }} → {{ $route?->origin->name }} (ขากลับ) · {{ thai_date($this->ret, false) }}</p>
            @forelse ($retTrips as $t)
                @include('livewire.partials.trip-card', ['t' => $t, 'action' => 'chooseReturn', 'route' => $t->route])
            @empty
                <p class="card p-6 text-slate-500">ไม่มีรอบขากลับที่เปิดจองในวันนี้</p>
            @endforelse
            <button wire:click="goBack" class="btn-line mt-4">‹ กลับไปเลือกขาไป</button>
        @else
            <h1 class="mb-3 text-2xl font-extrabold">ข้อมูลผู้จอง</h1>
            <form wire:submit="submit" class="space-y-4">
                <div>
                    <label class="label" for="n">ชื่อผู้จอง <span class="text-pink">*</span></label>
                    <input id="n" wire:model="contactName" class="field" placeholder="เช่น สมชาย ใจดี" autocomplete="name">
                    @error('contactName')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="p">เบอร์โทรศัพท์ <span class="text-pink">*</span></label>
                    <input id="p" wire:model="contactPhone" class="field" placeholder="เช่น 081-234-5678" inputmode="tel" autocomplete="tel">
                    @error('contactPhone')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="e">อีเมล <span class="text-pink">*</span></label>
                    <input id="e" wire:model="contactEmail" type="email" class="field" placeholder="เช่น somchai@example.com" autocomplete="email">
                    @error('contactEmail')<p class="err">{{ $message }}</p>@enderror
                </div>

                {{-- ตรวจท่าเรือ วันที่ เวลา และยอดรวมก่อนส่งคำขอ --}}
                <div class="card border-sea p-4">
                    <h2 class="mb-2 font-extrabold">ตรวจสอบก่อนส่งคำขอ</h2>
                    @foreach ([['ขาไป', $outTrip], ['ขากลับ', $isRound ? $retTrip : null]] as [$label, $tr])
                        @if ($tr)
                            <div class="border-b py-2 last:border-0">
                                <div class="font-bold">{{ $label }}: {{ $tr->route->label }}</div>
                                <div class="text-sm text-slate-600">{{ thai_date($tr->departs_at) }} · ออก {{ $tr->departs_at->format('H:i') }} น. ถึง {{ $tr->arrives_at->format('H:i') }} น.</div>
                                <div class="text-sm text-slate-600">ท่าขึ้นเรือ: {{ $tr->route->origin->name }} · {{ number_format($tr->price) }} บาท × {{ $pax }} คน = {{ number_format($tr->price * $pax) }} บาท</div>
                            </div>
                        @endif
                    @endforeach
                    <div class="mt-2 flex items-center justify-between text-lg font-extrabold">
                        <span>ยอดรวม</span><span class="text-pink">{{ number_format($total) }} บาท</span>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" wire:click="goBack" class="btn-line">‹ ย้อนกลับ</button>
                    <button class="btn-pink flex-1 py-3.5 text-lg" wire:loading.attr="disabled" wire:target="submit">
                        <span wire:loading.remove wire:target="submit">ส่งคำขอจอง ›</span>
                        <span wire:loading wire:target="submit">กำลังส่ง…</span>
                    </button>
                </div>
                <p class="text-xs text-slate-500">ส่งคำขอแล้วระบบจะกันที่นั่งให้ {{ config('ferry.hold_minutes') }} นาที เพื่อให้คุณโอนเงินและแจ้งโอนพร้อมแนบสลิป</p>
            </form>
        @endif
    </div>
</div>
