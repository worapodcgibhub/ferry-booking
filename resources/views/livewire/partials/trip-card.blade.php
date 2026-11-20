@php($ok = $t->isBookable($pax))
<div class="mb-3 flex flex-wrap items-center gap-3 rounded-2xl border-2 p-4 {{ $ok ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-100 text-slate-500' }}" wire:key="trip-{{ $action }}-{{ $t->id }}">
    <div class="min-w-[6.5rem] flex-1 sm:flex-none sm:min-w-[9rem]">
        <div class="text-xs font-bold text-slate-500">{{ $route->origin->name }}</div>
        <div class="text-2xl font-extrabold">{{ $t->departs_at->format('H:i') }} น.</div>
    </div>
    <div class="text-sea">→</div>
    <div class="min-w-[6.5rem] flex-1 sm:flex-none sm:min-w-[9rem]">
        <div class="text-xs font-bold text-slate-500">{{ $route->destination->name }}</div>
        <div class="text-2xl font-extrabold">{{ $t->arrives_at->format('H:i') }} น.</div>
    </div>
    <div class="ml-auto text-right">
        @if ($ok)
            <div><span class="text-3xl font-extrabold text-pink">{{ number_format($t->price) }}</span> <span class="font-bold">บาท/คน</span></div>
            <div class="text-xs {{ $t->seats_left < 6 ? 'font-bold text-pink' : 'text-slate-500' }}">เหลือ {{ $t->seats_left }} ที่นั่ง</div>
        @else
            <span class="badge bg-slate-400 px-4 py-1.5 text-sm text-white">{{ $t->seats_left < $pax && $t->seats_left > 0 ? 'ที่นั่งไม่พอ' : 'เต็มแล้ว' }}</span>
        @endif
    </div>
    <button wire:click="{{ $action }}({{ $t->id }})" class="btn-pink w-full" @disabled(! $ok)>เลือกเที่ยวนี้ ›</button>
</div>
