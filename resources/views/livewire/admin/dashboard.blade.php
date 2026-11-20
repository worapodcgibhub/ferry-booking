<div>
    <h1 class="mb-4 text-2xl font-extrabold">ภาพรวมวันนี้ · {{ thai_date(now(), false) }}</h1>
    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <a href="{{ route('admin.bookings', ['filter' => 'review']) }}" class="card p-4 hover:border-pink">
            <div class="text-sm text-slate-500">รอตรวจยอด</div><div class="text-4xl font-extrabold text-pink">{{ $waiting }}</div></a>
        <div class="card p-4"><div class="text-sm text-slate-500">รอลูกค้าแจ้งโอน</div><div class="text-4xl font-extrabold">{{ $unpaid }}</div></div>
        <div class="card p-4"><div class="text-sm text-slate-500">ผู้โดยสารยืนยันแล้ววันนี้</div><div class="text-4xl font-extrabold text-sea">{{ $confirmedToday }}</div></div>
    </div>
    <h2 class="mb-2 text-lg font-extrabold">รอบเรือวันนี้</h2>
    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-sea-50 text-left"><tr><th class="p-3">เวลา</th><th>เส้นทาง</th><th>ที่นั่งที่กัน</th><th>ยืนยันแล้ว (คน)</th><th>สถานะ</th></tr></thead>
            <tbody class="divide-y">
            @forelse ($trips as $t)
                <tr><td class="p-3 font-bold">{{ $t->departs_at->format('H:i') }}</td><td>{{ $t->route->label }}</td>
                    <td>{{ $t->seats_taken }}/{{ $t->capacity }}</td><td>{{ $confirmed[$t->id] ?? 0 }}</td><td>{{ $t->status === 'open' ? 'เปิดจอง' : 'ปิดจอง' }}</td></tr>
            @empty
                <tr><td class="p-4 text-slate-500" colspan="5">วันนี้ไม่มีรอบเรือ</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
