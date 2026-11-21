<div>
    <h1 class="mb-4 text-2xl font-extrabold">รอบเรือ</h1>
    @if ($flash)<div class="mb-3 rounded-control bg-green-50 p-3 font-semibold text-green-800">{{ $flash }}</div>@endif
    @if ($error)<div class="mb-3 rounded-control bg-pink-50 p-3 font-semibold text-pink">{{ $error }}</div>@endif

    <form wire:submit="generate" class="card mb-5 grid gap-3 p-4 md:grid-cols-6">
        <h2 class="font-extrabold md:col-span-6">สร้างรอบเรือเป็นชุด</h2>
        <div class="md:col-span-2"><label class="label">เส้นทาง *</label>
            <select wire:model="routeId" class="field"><option value="">— เลือก —</option>@foreach ($routes as $r)<option value="{{ $r->id }}">{{ $r->label }}</option>@endforeach</select>
            @error('routeId')<p class="err">{{ $message }}</p>@enderror</div>
        <div><label class="label">ตั้งแต่วันที่</label><input type="date" wire:model="fromDate" class="field">@error('fromDate')<p class="err">{{ $message }}</p>@enderror</div>
        <div><label class="label">ถึงวันที่</label><input type="date" wire:model="toDate" class="field">@error('toDate')<p class="err">{{ $message }}</p>@enderror</div>
        <div class="md:col-span-2"><label class="label">เวลาออก (คั่นด้วยจุลภาค)</label><input wire:model="times" class="field">@error('times')<p class="err">{{ $message }}</p>@enderror</div>
        <div><label class="label">ราคา/คน (บาท)</label><input type="number" wire:model="price" class="field">@error('price')<p class="err">{{ $message }}</p>@enderror</div>
        <div><label class="label">โควตาที่นั่ง</label><input type="number" wire:model="capacity" class="field">@error('capacity')<p class="err">{{ $message }}</p>@enderror</div>
        <div class="md:col-span-2"><label class="label">ผู้ให้บริการ</label><input wire:model="provider" class="field"></div>
        <div class="md:col-span-6">
            <span class="label">เฉพาะวัน (ไม่เลือก = ทุกวัน)</span>
            <div class="flex flex-wrap gap-3 text-sm">
                @foreach (['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'] as $i => $d)
                    <label class="flex items-center gap-1"><input type="checkbox" wire:model="weekdays" value="{{ $i }}"> {{ $d }}</label>
                @endforeach
            </div>
        </div>
        <div class="md:col-span-6"><button class="btn-pink" wire:loading.attr="disabled" wire:target="generate">สร้างและเผยแพร่รอบเรือ</button></div>
    </form>

    <div class="mb-3 flex flex-wrap gap-2">
        <select wire:model.live="filterRoute" class="field max-w-xs"><option value="">ทุกเส้นทาง</option>@foreach ($routes as $r)<option value="{{ $r->id }}">{{ $r->label }}</option>@endforeach</select>
        <input type="date" wire:model.live="filterDate" class="field max-w-[12rem]">
    </div>
    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-sea-50 text-left"><tr><th class="p-3">ออกเดินทาง</th><th>เส้นทาง</th><th>ราคา</th><th>จอง/โควตา</th><th>สถานะ</th><th></th></tr></thead>
            <tbody class="divide-y">
            @foreach ($trips as $t)
                @if ($editingId === $t->id)
                    <tr wire:key="e-{{ $t->id }}" class="bg-amber-50">
                        <td class="p-2"><input type="datetime-local" wire:model="editDeparts" class="field"></td>
                        <td>{{ $t->route->label }}</td>
                        <td><input type="number" wire:model="editPrice" class="field w-24"></td>
                        <td><input type="number" wire:model="editCapacity" class="field w-24"> <span class="text-xs">จองแล้ว {{ $t->seats_taken }}</span></td>
                        <td><select wire:model="editStatus" class="field"><option value="open">เปิดจอง</option><option value="closed">ปิดจอง</option></select></td>
                        <td class="whitespace-nowrap"><button wire:click="saveEdit" class="btn-pink !px-3 !py-1.5 text-xs">บันทึก</button> <button wire:click="$set('editingId', null)" class="btn-line !px-3 !py-1.5 text-xs">ยกเลิก</button></td>
                    </tr>
                @else
                    <tr wire:key="t-{{ $t->id }}">
                        <td class="p-3 font-bold">{{ thai_datetime($t->departs_at) }}</td><td>{{ $t->route->label }}</td>
                        <td>{{ number_format($t->price) }}</td><td>{{ $t->seats_taken }}/{{ $t->capacity }}</td>
                        <td>{{ $t->status === 'open' ? 'เปิดจอง' : 'ปิดจอง' }}</td>
                        <td class="whitespace-nowrap"><button wire:click="edit({{ $t->id }})" class="btn-line !px-3 !py-1 text-xs">แก้ไข</button>
                            <button wire:click="deleteTrip({{ $t->id }})" wire:confirm="ลบรอบเรือนี้?" class="btn-line !px-3 !py-1 text-xs !text-pink">ลบ</button></td>
                    </tr>
                @endif
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $trips->links() }}</div>
</div>
