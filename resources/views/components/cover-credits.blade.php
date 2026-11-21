@php
    // เครดิตผู้ถ่ายภาพปกสาธิต: แสดงเฉพาะเกาะที่ยังใช้ภาพที่มีเครดิตอยู่ (ถ้าแอดมินอัปโหลดรูปของตัวเอง เครดิตของเกาะนั้นจะหายไปเอง)
    $credits = config('ferry.cover_credits', []);
    $islands = $credits
        ? \App\Models\Island::where('is_active', true)->whereIn('cover_path', array_keys($credits))->orderBy('sort_order')->orderBy('id')->get()
        : collect();
@endphp
@if ($islands->isNotEmpty())
    <div class="mx-auto max-w-6xl px-4 pb-6 text-xs leading-relaxed text-slate-500" data-cover-credits>
        <span class="font-semibold">เครดิตภาพปก (Wikimedia Commons):</span>
        @foreach ($islands as $island)
            @php($c = $credits[$island->cover_path])
            {{ $island->name }} —
            <a href="{{ $c['source_url'] }}" target="_blank" rel="noopener" class="underline">“{{ $c['title'] }}”</a>
            โดย {{ $c['author'] }}
            (<a href="{{ $c['license_url'] }}" target="_blank" rel="noopener" class="underline">{{ $c['license'] }}</a>)@if (! $loop->last) · @endif
        @endforeach
    </div>
@endif
