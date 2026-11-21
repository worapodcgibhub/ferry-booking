<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'เรือไปเกาะ — จองเรือไปเกาะออนไลน์' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50">
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur" x-data="{ open: false }">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <x-ship-logo class="h-9 w-9" />
                <span class="text-2xl font-extrabold text-navy">เรือไปเกาะ</span>
            </a>
            <nav class="hidden gap-6 text-sm font-semibold md:flex">
                <a href="{{ route('home') }}" class="border-b-2 border-pink pb-1 text-pink">หน้าแรก</a>
                <a href="{{ route('home') }}#schedule" class="hover:text-pink">ตารางเรือ</a>
                <a href="{{ route('home') }}#ports" class="hover:text-pink">ข้อมูลท่าเรือ</a>
                <a href="{{ route('home') }}#help" class="hover:text-pink">ช่วยเหลือ</a>
            </nav>
            <button class="md:hidden" @click="open = !open" aria-label="เมนู">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
        <nav x-show="open" x-cloak class="flex flex-col gap-3 border-t px-4 py-3 text-sm font-semibold md:hidden">
            <a href="{{ route('home') }}">หน้าแรก</a>
            <a href="{{ route('home') }}#schedule">ตารางเรือ</a>
            <a href="{{ route('home') }}#ports">ข้อมูลท่าเรือ</a>
            <a href="{{ route('home') }}#help">ช่วยเหลือ</a>
        </nav>
    </header>

    {{ $slot }}

    <footer id="help" class="mt-12 border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 text-sm text-slate-600 md:flex-row md:justify-between">
            <p>ช่วยเหลือ: ลูกค้าโอนเงินเอง → แจ้งโอนพร้อมแนบสลิป → ทีมงานตรวจยอด → ยืนยันและส่งเลขจองทางอีเมล</p>
            <p>ระบบทดสอบในเครื่อง · ข้อมูลทั้งหมดเป็นข้อมูลสมมติ · <a class="underline" href="{{ route('login') }}">เจ้าหน้าที่</a></p>
        </div>
        <x-cover-credits />
    </footer>
</body>
</html>
