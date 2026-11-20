<div>
    <h1 class="mb-4 text-2xl font-extrabold">ผู้ใช้งาน</h1>
    <p class="mb-3 text-sm text-slate-500">มี 2 บทบาท: เจ้าของระบบ (จัดการทุกอย่าง) และเจ้าหน้าที่ (ตรวจยอด ยืนยัน ยกเลิก ดูผู้โดยสาร)</p>
    @if ($flash)<div class="mb-3 rounded-xl bg-green-50 p-3 font-semibold text-green-800">{{ $flash }}</div>@endif
    @if ($error)<div class="mb-3 rounded-xl bg-pink-50 p-3 font-semibold text-pink">{{ $error }}</div>@endif

    <form wire:submit="create" class="card mb-5 grid gap-3 p-4 md:grid-cols-4">
        <div><label class="label">ชื่อ</label><input wire:model="name" class="field">@error('name')<p class="err">{{ $message }}</p>@enderror</div>
        <div><label class="label">อีเมล</label><input wire:model="email" type="email" class="field">@error('email')<p class="err">{{ $message }}</p>@enderror</div>
        <div><label class="label">รหัสผ่าน</label><input wire:model="password" type="password" class="field">@error('password')<p class="err">{{ $message }}</p>@enderror</div>
        <div><label class="label">บทบาท</label><select wire:model="role" class="field"><option value="staff">เจ้าหน้าที่</option><option value="owner">เจ้าของระบบ</option></select></div>
        <div class="md:col-span-4"><button class="btn-pink">+ เพิ่มผู้ใช้งาน</button></div>
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-sea-50 text-left"><tr><th class="p-3">ชื่อ</th><th>อีเมล</th><th>บทบาท</th><th></th></tr></thead>
            <tbody class="divide-y">
            @foreach ($users as $u)
                <tr wire:key="u-{{ $u->id }}"><td class="p-3 font-bold">{{ $u->name }}</td><td>{{ $u->email }}</td>
                    <td>{{ $u->role === 'owner' ? 'เจ้าของระบบ' : 'เจ้าหน้าที่' }}</td>
                    <td class="space-x-1 whitespace-nowrap">
                        @if ($u->id !== auth()->id())
                            <button wire:click="setRole({{ $u->id }}, '{{ $u->role === 'owner' ? 'staff' : 'owner' }}')" class="btn-line !px-3 !py-1 text-xs">เปลี่ยนเป็น{{ $u->role === 'owner' ? 'เจ้าหน้าที่' : 'เจ้าของระบบ' }}</button>
                            <button wire:click="remove({{ $u->id }})" wire:confirm="ลบผู้ใช้นี้?" class="btn-line !px-3 !py-1 text-xs !text-pink">ลบ</button>
                        @else <span class="text-xs text-slate-400">(คุณ)</span> @endif
                    </td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
