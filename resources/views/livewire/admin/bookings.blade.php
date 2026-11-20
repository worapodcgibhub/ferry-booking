<div>
    <h1 class="mb-4 text-2xl font-extrabold">รายการจอง</h1>
    <div class="mb-3 flex flex-wrap gap-2">
        <input type="search" wire:model.live.debounce.300ms="q" class="field max-w-xs" placeholder="ค้นหาเลขจอง ชื่อ เบอร์ อีเมล">
        <select wire:model.live="filter" class="field max-w-[14rem]">
            <option value="all">ทั้งหมด</option>
            <option value="review">รอตรวจยอด</option>
            <option value="pending">รอดำเนินการทั้งหมด</option>
            <option value="confirmed">ยืนยันแล้ว</option>
            <option value="closed">ยกเลิก/หมดเวลา</option>
        </select>
    </div>
    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-sea-50 text-left"><tr><th class="p-3">เลขจอง</th><th>ผู้จอง</th><th>ประเภท</th><th>คน</th><th>ยอด</th><th>สถานะการจอง</th><th>การชำระเงิน</th><th>เวลาจอง</th></tr></thead>
            <tbody class="divide-y">
            @forelse ($bookings as $b)
                <tr wire:key="b-{{ $b->id }}" class="hover:bg-slate-50">
                    <td class="p-3"><a class="font-bold text-pink underline" href="{{ route('admin.bookings.show', $b) }}">{{ $b->reference }}</a></td>
                    <td>{{ $b->contact_name }}<div class="text-xs text-slate-500">{{ $b->contact_phone }}</div></td>
                    <td>{{ $b->trip_type === 'round' ? 'ไป–กลับ' : 'เที่ยวเดียว' }}</td>
                    <td>{{ $b->passengers }}</td>
                    <td>{{ number_format($b->total_amount) }}</td>
                    <td><span class="badge {{ ['pending' => 'bg-amber-100 text-amber-800', 'confirmed' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-slate-200', 'expired' => 'bg-slate-200'][$b->booking_status] }}">{{ $b->booking_status_label }}</span></td>
                    <td><span class="badge {{ ['unpaid' => 'bg-slate-200', 'submitted' => 'bg-sky-100 text-sky-800', 'verified' => 'bg-green-100 text-green-800', 'rejected' => 'bg-pink-50 text-pink'][$b->payment_status] }}">{{ $b->payment_status_label }}</span></td>
                    <td class="text-xs">{{ thai_datetime($b->created_at) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-6 text-center text-slate-500">ไม่พบรายการ</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $bookings->links() }}</div>
</div>
