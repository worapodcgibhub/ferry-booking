<div>
    {{-- ภาพปก: เปลี่ยนพร้อมชื่อเกาะ/ปลายทาง/รอบเรือ เมื่อสลับเกาะ --}}
    <section class="relative">
        <div wire:key="cover-{{ $island?->id }}" class="relative h-[26rem] overflow-hidden bg-sea-100 md:h-[30rem]">
            <img data-hero-cover src="{{ $island?->cover_url }}" alt="{{ $island?->name }}" class="absolute inset-0 h-full w-full object-cover">
            {{-- ม่านขาวชั้นเดียวคลุมทั้ง hero (ไล่ซ้าย→ขวา; มือถือไล่บน→ล่าง): ค่าอยู่ที่ .hero-veil ใน app.css (--hero-veil-peak / --hero-veil-end) --}}
            <div class="hero-veil absolute inset-0"></div>
            <div class="relative mx-auto max-w-6xl px-4 pt-8 md:pt-12">
                <h1 class="hero-title text-5xl font-extrabold leading-tight text-navy md:text-7xl">{{ $island?->headline_text ?? 'จองเรือไปเกาะ' }}</h1>
                <div class="mt-2 h-2 w-40 rounded-full bg-sea/80 md:w-64"></div>
                @if ($island?->description)<p class="hero-desc mt-3 max-w-lg text-lg font-semibold text-navy/80 md:text-xl">{{ $island->description }}</p>@endif
            </div>
        </div>

        <div class="relative z-10 mx-auto -mt-28 max-w-6xl px-4">
            {{-- แท็บเกาะ --}}
            <div class="mb-3 flex flex-wrap gap-2">
                @foreach ($islands as $i)
                    <button type="button" wire:click="selectIsland('{{ $i->slug }}')" wire:key="tab-{{ $i->id }}"
                        class="flex items-center gap-2 rounded-card px-5 py-3 text-lg font-extrabold shadow transition {{ $i->id === $island?->id ? 'bg-pink text-white' : 'bg-white text-navy hover:bg-pink-50' }}">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 22V10M12 10C9 9 6 10 4 12M12 10c3-1 6 0 8 2M12 10c0-3-2-5-5-5M12 10c0-3 2-5 5-5"/></svg>
                        {{ $i->name }}
                    </button>
                @endforeach
            </div>

            {{-- การ์ดค้นหา (ตำแหน่งคงที่เมื่อสลับเกาะ) --}}
            <form wire:submit="search" class="card grid grid-cols-2 gap-3 p-4 md:grid-cols-12 md:p-5">
                <div class="col-span-2 md:col-span-3">
                    <label class="label" for="origin">เดินทางจาก</label>
                    <select id="origin" wire:model.live="originId" class="field font-bold">
                        @foreach ($originPorts as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-span-2 -my-2 flex items-end justify-center md:col-span-1 md:my-0">
                    <button type="button" wire:click="swap" class="rounded-full p-2 text-sea hover:bg-sea-50" aria-label="สลับต้นทางปลายทาง" title="สลับต้นทาง/ปลายทาง">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h13M16 3l4 4-4 4M17 17H4M8 13l-4 4 4 4"/></svg>
                    </button>
                </div>
                <div class="col-span-2 md:col-span-4">
                    <label class="label" for="dest">เดินทางไป</label>
                    <select id="dest" wire:model="destinationId" class="field font-bold">
                        @foreach ($destPorts as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                    </select>
                    @error('destinationId')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="col-span-2 md:col-span-4">
                    <label class="label" for="type">ประเภทการเดินทาง</label>
                    <select id="type" wire:model.live="tripType" class="field font-bold">
                        <option value="round">ไป–กลับ</option>
                        <option value="oneway">เที่ยวเดียว</option>
                    </select>
                    @error('tripType')<p class="err">{{ $message }}</p>@enderror
                </div>

                <div class="col-span-1 {{ $tripType === 'round' ? 'md:col-span-3' : 'md:col-span-4' }}">
                    <label class="label" for="d1">วันเดินทาง{{ $tripType === 'round' ? 'ไป' : '' }}</label>
                    <input id="d1" type="date" wire:model.live="departDate" min="{{ now()->toDateString() }}" class="field">
                    @error('departDate')<p class="err">{{ $message }}</p>@enderror
                </div>
                @if ($tripType === 'round')
                    <div class="col-span-1 md:col-span-3">
                        <label class="label" for="d2">วันเดินทางกลับ</label>
                        <input id="d2" type="date" wire:model.live="returnDate" min="{{ $departDate }}" class="field">
                        @error('returnDate')<p class="err">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div class="{{ $tripType === 'round' ? 'col-span-2' : 'col-span-1' }} md:col-span-2">
                    <label class="label" for="pax">ผู้โดยสาร</label>
                    <select id="pax" wire:model="passengers" class="field font-bold">
                        @for ($n = 1; $n <= config('ferry.max_passengers'); $n++)<option value="{{ $n }}">{{ $n }} คน</option>@endfor
                    </select>
                </div>
                <div class="col-span-2 flex items-end md:col-span-4">
                    <button class="btn-pink w-full py-3.5 text-lg" wire:loading.attr="disabled" wire:target="search">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        ค้นหาเที่ยวเรือ
                    </button>
                </div>
            </form>
        </div>
    </section>

    {{-- ตัวอย่างตารางเรือ --}}
    <section id="schedule" class="mx-auto mt-10 max-w-6xl px-4">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-2xl font-extrabold">ตารางเรือ {{ $island?->name }}</h2>
            @if ($route)<span class="badge bg-sea-150 text-navy">{{ $route->label }}</span>@endif
        </div>
        <div class="card overflow-hidden">
            @if ($trips->isEmpty())
                <p class="p-6 text-slate-500">ยังไม่มีรอบเรือที่เปิดจองสำหรับเส้นทางนี้</p>
            @else
                <p class="bg-sea-150 px-4 py-2 text-sm font-bold">รอบวันที่ {{ thai_date($scheduleDate) }}</p>
                <div class="divide-y">
                    @foreach ($trips as $t)
                        <div class="flex items-center gap-3 px-4 py-3" wire:key="t-{{ $t->id }}">
                            <div class="w-24 text-lg font-bold">{{ $t->departs_at->format('H:i') }} น.</div>
                            <div class="hidden w-24 text-slate-600 sm:block">ถึง {{ $t->arrives_at->format('H:i') }} น.</div>
                            <div class="hidden flex-1 text-sm text-slate-600 md:block">ประมาณ {{ intdiv($route->duration_minutes, 60) }} ชม. {{ $route->duration_minutes % 60 }} นาที · {{ $t->provider }}</div>
                            <div class="flex-1 text-right text-sm md:flex-none"><b class="text-pink">{{ number_format($t->price) }}</b> บาท/คน
                                <span class="ml-1 text-xs {{ $t->seats_left < 5 ? 'text-pink' : 'text-slate-500' }}">{{ $t->seats_left > 0 ? 'เหลือ '.$t->seats_left.' ที่' : 'เต็ม' }}</span></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- คู่มือท่าเรือ --}}
    <section id="ports" class="mx-auto mt-10 max-w-6xl px-4">
        <h2 class="mb-3 text-2xl font-extrabold">คู่มือท่าเรือ</h2>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($guidePorts as $p)
                <div class="card flex gap-4 p-4" wire:key="p-{{ $p->id }}">
                    <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-control bg-sea-100 text-sea">
                        <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-extrabold">{{ $p->name }}</h3>
                        <p class="text-sm text-slate-600">{{ $p->location }}</p>
                        @if ($p->boarding_point)<p class="mt-1 text-sm"><b>{{ $p->boarding_point }}</b>@if($p->note) · {{ $p->note }}@endif</p>@endif
                        @if ($p->map_url)<a href="{{ $p->map_url }}" target="_blank" rel="noopener" class="mt-1 inline-block text-sm font-semibold text-pink underline">ดูแผนที่</a>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
