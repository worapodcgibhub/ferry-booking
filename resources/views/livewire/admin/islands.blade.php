<div>
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-extrabold">เกาะและพื้นที่</h1>
        <button wire:click="create" class="btn-pink">+ เพิ่มเกาะ</button>
    </div>
    @if ($flash)<div class="mb-3 rounded-control bg-green-50 p-3 font-semibold text-green-800">{{ $flash }}</div>@endif

    @if ($showForm)
        <form wire:submit="save" class="card mb-5 grid gap-3 p-4 md:grid-cols-2">
            <h2 class="font-extrabold md:col-span-2">{{ $editingId ? 'แก้ไขเกาะ' : 'เพิ่มเกาะใหม่' }}</h2>
            <div><label class="label">ชื่อเกาะ *</label><input wire:model="name" class="field">@error('name')<p class="err">{{ $message }}</p>@enderror</div>
            <div><label class="label">หัวข้อหน้าแรก (เช่น ไปเกาะช้าง)</label><input wire:model="headline" class="field" placeholder="ไม่กรอก = ไป+ชื่อเกาะ"></div>
            <div class="md:col-span-2"><label class="label">คำบรรยายสั้น</label><input wire:model="description" class="field"></div>
            <div>
                <label class="label">พื้นที่</label>
                <select wire:model="areaId" class="field"><option value="">— ไม่ระบุ —</option>@foreach ($areas as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select>
                <div class="mt-2 flex gap-2"><input wire:model="newArea" class="field" placeholder="เพิ่มพื้นที่ใหม่"><button type="button" wire:click="addArea" class="btn-line">เพิ่ม</button></div>
                @error('newArea')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">ลำดับแท็บ</label><input type="number" wire:model="sortOrder" class="field"></div>
                <label class="mt-7 flex items-center gap-2 font-semibold"><input type="checkbox" wire:model="isActive" class="h-5 w-5"> เปิดให้บริการ</label>
            </div>
            <div class="md:col-span-2">
                <label class="label">รูปปก (ภาพแนวนอน ไม่เกิน 4 MB)</label>
                <input type="file" wire:model="cover" accept="image/*" class="field">
                <div wire:loading wire:target="cover" class="text-sm text-slate-500">กำลังอัปโหลด…</div>
                @error('cover')<p class="err">{{ $message }}</p>@enderror
                <div class="mt-2 flex gap-3">
                    @if ($cover)<img src="{{ $cover->temporaryUrl() }}" class="h-24 rounded-control object-cover" alt="ตัวอย่างรูปปกใหม่">
                    @elseif ($currentCover)<img src="{{ $currentCover }}" class="h-24 rounded-control object-cover" alt="รูปปกปัจจุบัน">@endif
                </div>
            </div>
            <div class="flex gap-2 md:col-span-2"><button class="btn-pink" wire:loading.attr="disabled" wire:target="save,cover">บันทึก</button><button type="button" wire:click="resetForm" class="btn-line">ยกเลิก</button></div>
        </form>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($islands as $i)
            <div class="card overflow-hidden" wire:key="i-{{ $i->id }}">
                <img src="{{ $i->cover_url }}" alt="{{ $i->name }}" class="h-32 w-full object-cover">
                <div class="p-3">
                    <div class="flex items-center justify-between"><h3 class="text-lg font-extrabold">{{ $i->name }}</h3>
                        <span class="badge {{ $i->is_active ? 'bg-green-100 text-green-800' : 'bg-slate-200' }}">{{ $i->is_active ? 'เปิด' : 'ปิด' }}</span></div>
                    <p class="text-sm text-slate-500">{{ $i->area?->name ?? 'ไม่ระบุพื้นที่' }} · ลำดับ {{ $i->sort_order }}</p>
                    <button wire:click="edit({{ $i->id }})" class="btn-line mt-2 !py-1.5 text-sm">แก้ไข</button>
                </div>
            </div>
        @endforeach
    </div>
</div>
