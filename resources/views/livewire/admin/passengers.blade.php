<div>
    <h1 class="mb-4 text-2xl font-extrabold">ผู้โดยสารตามรอบ</h1>
    <div class="mb-4 flex flex-wrap gap-2">
        <input type="date" wire:model.live="date" class="field max-w-[12rem]">
        <select wire:model.live="tripId" class="field max-w-md">
            @foreach ($trips as $t)<option value="{{ $t->id }}" @selected($trip?->id === $t->id)>{{ $t->departs_at->format('H:i') }} · {{ $t->route->label }}</option>@endforeach
        </select>
    </div>
    @if ($trip)
        <div class="mb-3 grid gap-3 sm:grid-cols-3">
            <div class="card p-3"><div class="text-sm text-slate-500">ผู้โดยสารที่ยืนยันแล้ว</div><div class="text-3xl font-extrabold text-sea">{{ $confirmedPax }}</div></div>
            <div class="card p-3"><div class="text-sm text-slate-500">ที่นั่งที่กันไว้ (รวมรอโอน)</div><div class="text-3xl font-extrabold">{{ $trip->seats_taken }}/{{ $trip->capacity }}</div></div>
            <div class="card p-3"><div class="text-sm text-slate-500">ท่าขึ้นเรือ</div><div class="font-extrabold">{{ $trip->route->origin->name }}</div></div>
        </div>
        <div class="card overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-sea-50 text-left"><tr><th class="p-3">เลขจอง</th><th>ผู้จอง</th><th>เบอร์</th><th>คน</th><th>ขา</th><th>สถานะ</th></tr></thead>
                <tbody class="divide-y">
                @forelse ($legs as $l)
                    <tr wire:key="l-{{ $l->id }}"><td class="p-3"><a class="font-bold text-pink underline" href="{{ route('admin.bookings.show', $l->booking_id) }}">{{ $l->booking->reference }}</a></td>
                        <td>{{ $l->booking->contact_name }}</td><td>{{ $l->booking->contact_phone }}</td><td>{{ $l->passengers }}</td>
                        <td>{{ $l->direction === 'return' ? 'กลับ' : 'ไป' }}</td><td>{{ $l->booking->booking_status_label }} / {{ $l->booking->payment_status_label }}</td></tr>
                @empty
                    <tr><td colspan="6" class="p-4 text-slate-500">ยังไม่มีรายการจองในรอบนี้</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @else
        <p class="card p-6 text-slate-500">ไม่มีรอบเรือในวันที่เลือก</p>
    @endif
</div>
