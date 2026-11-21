<div>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-extrabold">เส้นทาง</h1>
        <button wire:click="create" class="btn-pink">+ เพิ่มเส้นทาง</button>
    </div>
    <p class="mb-3 text-sm text-slate-500">เส้นทางกำหนดจากท่าต้นทางไปท่าปลายทาง รองรับทั้งฝั่งไปเกาะ เกาะกลับฝั่ง และเกาะไปเกาะ</p>
    @if ($flash)<div class="mb-3 rounded-control bg-green-50 p-3 font-semibold text-green-800">{{ $flash }}</div>@endif

    @if ($showForm)
        <form wire:submit="save" class="card mb-5 grid gap-3 p-4 md:grid-cols-2">
            <div><label class="label">ท่าต้นทาง *</label><select wire:model="originId" class="field"><option value="">— เลือก —</option>@foreach ($ports as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>@error('originId')<p class="err">{{ $message }}</p>@enderror</div>
            <div><label class="label">ท่าปลายทาง *</label><select wire:model="destinationId" class="field"><option value="">— เลือก —</option>@foreach ($ports as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>@error('destinationId')<p class="err">{{ $message }}</p>@enderror</div>
            <div><label class="label">เกาะที่ให้บริการ (แท็บในหน้าแรก) *</label><select wire:model="islandId" class="field"><option value="">— เลือก —</option>@foreach ($islands as $i)<option value="{{ $i->id }}">{{ $i->name }}</option>@endforeach</select>@error('islandId')<p class="err">{{ $message }}</p>@enderror</div>
            <div><label class="label">ระยะเวลา (นาที)</label><input type="number" wire:model="duration" class="field">@error('duration')<p class="err">{{ $message }}</p>@enderror</div>
            <label class="flex items-center gap-2 font-semibold"><input type="checkbox" wire:model="isActive" class="h-5 w-5"> เปิดใช้งาน</label>
            @unless ($editingId)<label class="flex items-center gap-2 font-semibold"><input type="checkbox" wire:model="createReverse" class="h-5 w-5"> สร้างเส้นทางขากลับให้ด้วย</label>@endunless
            <div class="flex gap-2 md:col-span-2"><button class="btn-pink">บันทึก</button><button type="button" wire:click="resetForm" class="btn-line">ยกเลิก</button></div>
        </form>
    @endif

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-sea-50 text-left"><tr><th class="p-3">เส้นทาง</th><th>เกาะ</th><th>ระยะเวลา</th><th>สถานะ</th><th></th></tr></thead>
            <tbody class="divide-y">
            @foreach ($routes as $r)
                <tr wire:key="r-{{ $r->id }}"><td class="p-3 font-bold">{{ $r->label }}</td><td>{{ $r->island->name }}</td><td>{{ $r->duration_minutes }} นาที</td>
                    <td>{{ $r->is_active ? 'เปิด' : 'ปิด' }}</td><td><button wire:click="edit({{ $r->id }})" class="btn-line !py-1 text-xs">แก้ไข</button></td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
