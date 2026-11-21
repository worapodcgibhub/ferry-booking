<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'หลังบ้าน' }} — เรือไปเกาะ</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100" x-data="{ menu: false }">
@php
    $u = auth()->user();
@endphp
<div class="flex min-h-screen">
    <aside class="fixed inset-y-0 left-0 z-40 w-60 -translate-x-full bg-navy text-white transition md:static md:translate-x-0" :class="{ 'translate-x-0': menu }">
        <div class="flex items-center gap-2 px-4 py-4">
            <x-ship-logo class="h-8 w-8 rounded-micro bg-white p-0.5" />
            <span class="text-lg font-extrabold">หลังบ้าน</span>
        </div>
        <nav class="flex flex-col gap-1 px-2 text-sm font-semibold">
            @php
                $items = [
                    ['admin.dashboard', 'ภาพรวมวันนี้', false],
                    ['admin.bookings', 'รายการจอง', false],
                    ['admin.passengers', 'ผู้โดยสารตามรอบ', false],
                    ['admin.islands', 'เกาะและพื้นที่', true],
                    ['admin.ports', 'ท่าเรือและจุดขึ้นเรือ', true],
                    ['admin.routes', 'เส้นทาง', true],
                    ['admin.trips', 'รอบเรือ', true],
                    ['admin.users', 'ผู้ใช้งาน', true],
                ];
            @endphp
            @foreach ($items as [$r, $label, $ownerOnly])
                @if (! $ownerOnly || $u->isOwner())
                    <a href="{{ route($r) }}" class="rounded-small px-3 py-2 hover:bg-white/10 {{ request()->routeIs($r.'*') ? 'bg-white/15' : '' }}">{{ $label }}</a>
                @endif
            @endforeach
        </nav>
        <div class="absolute inset-x-0 bottom-0 border-t border-white/10 p-3 text-xs">
            <div class="font-semibold">{{ $u->name }}</div>
            <div class="opacity-70">{{ $u->isOwner() ? 'เจ้าของระบบ' : 'เจ้าหน้าที่' }}</div>
            <div class="mt-2 flex gap-3">
                <a href="{{ route('home') }}" class="underline">เว็บลูกค้า</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="underline">ออกจากระบบ</button></form>
            </div>
        </div>
    </aside>
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-3 border-b bg-white px-4 py-3 md:hidden">
            <button @click="menu = !menu" aria-label="เมนู"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
            <span class="font-bold">{{ $title ?? 'หลังบ้าน' }}</span>
        </div>
        <main class="mx-auto max-w-6xl p-4 md:p-6">{{ $slot }}</main>
    </div>
</div>
</body>
</html>
