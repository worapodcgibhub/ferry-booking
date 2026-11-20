<div>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-extrabold">ท่าเรือและจุดขึ้นเรือ</h1>
        <button wire:click="create" class="btn-pink">+ เพิ่มท่าเรือ</button>
    </div>
    @if ($flash)<div class="mb-3 rounded-xl bg-green-50 p-3 font-semibold text-green-800">{{ $flash }}</div>@endif

    @if ($showForm)
        <form wire:submit="save" class="card mb-5 grid gap-3 p-4 md:grid-cols-2">
            <h2 class="font-extrabold md:col-span-2">{{ $editingId ? 'แก้ไขท่าเรือ' : 'เพิ่มท่าเรือ' }}</h2>
            <div><label class="label">ชื่อท่าเรือ *</label><input wire:model="name" class="field">@error('name')<p class="err">{{ $message }}</p>@enderror</div>
            <div><label class="label">ที่ตั้ง</label><input wire:model="location" class="field" placeholder="อ.เมืองตราด จ.ตราด"></div>
            <div><label class="label">จุดขึ้นเรือ</label><input wire:model="boardingPoint" class="field"></div>
            <div><label class="label">ลิงก์แผนที่</label><input wire:model="mapUrl" class="field" placeholder="https://maps.google.com/...">@error('mapUrl')<p class="err">{{ $message }}</p>@enderror</div>
            <div class="md:col-span-2"><label class="label">หมายเหตุ/สิ่งอำนวยความสะดวก</label><input wire:model="note" class="field"></div>
            <div><label class="label">พื้นที่</label><select wire:model="areaId" class="field"><option value="">— ไม่ระบุ —</option>@foreach ($areas as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></div>
            <div><label class="label">ท่านี้อยู่บนเกาะ (ถ้าเป็นท่าฝั่งเกาะ)</label><select wire:model="islandId" class="field"><option value="">— ท่าฝั่งแผ่นดิน —</option>@foreach ($islands as $i)<option value="{{ $i->id }}">{{ $i->name }}</option>@endforeach</select></div>
            <label class="flex items-center gap-2 font-semibold"><input type="checkbox" wire:model="isActive" class="h-5 w-5"> เปิดใช้งาน</label>
            <div class="flex gap-2 md:col-span-2"><button class="btn-pink">บันทึก</button><button type="button" wire:click="resetForm" class="btn-line">ยกเลิก</button></div>
        </form>
    @endif

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-sea-50 text-left"><tr><th class="p-3">ท่าเรือ</th><th>ที่ตั้ง</th><th>จุดขึ้นเรือ</th><th>เกาะ</th><th>แผนที่</th><th>สถานะ</th><th></th></tr></thead>
            <tbody class="divide-y">
            @foreach ($ports as $p)
                <tr wire:key="p-{{ $p->id }}"><td class="p-3 font-bold">{{ $p->name }}</td><td>{{ $p->location }}</td><td>{{ $p->boarding_point }}</td>
                    <td>{{ $p->island?->name ?? '—' }}</td>
                    <td>@if($p->map_url)<a href="{{ $p->map_url }}" target="_blank" rel="noopener" class="text-pink underline">เปิด</a>@endif</td>
                    <td>{{ $p->is_active ? 'เปิด' : 'ปิด' }}</td>
                    <td><button wire:click="edit({{ $p->id }})" class="btn-line !py-1 text-xs">แก้ไข</button></td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
