<div class="mx-auto max-w-md px-4 py-10">
    <div class="card p-6">
        <h1 class="text-2xl font-extrabold">ตรวจสอบการจอง / แจ้งโอนเงิน</h1>
        <p class="mt-1 text-sm text-slate-600">กรอกเลขอ้างอิงการจองและเบอร์โทรหรืออีเมลที่ใช้จอง เพื่อกลับไปดูสถานะหรือแจ้งโอนพร้อมแนบสลิป</p>
        <form wire:submit="search" class="mt-4 space-y-3">
            @if ($error)<div class="rounded-control bg-pink-50 p-3 font-semibold text-pink" role="alert">{{ $error }}</div>@endif
            <div>
                <label class="label" for="ref">เลขอ้างอิงการจอง</label>
                <input id="ref" wire:model="reference" class="field uppercase" placeholder="เช่น FB261125-ABCDE" autocomplete="off">
                @error('reference')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="contact">เบอร์โทรหรืออีเมลที่ใช้จอง</label>
                <input id="contact" wire:model="contact" class="field" placeholder="081-234-5678 หรือ you@example.com" autocomplete="off">
                @error('contact')<p class="err">{{ $message }}</p>@enderror
            </div>
            <button class="btn-pink w-full py-3.5 text-lg" wire:loading.attr="disabled" wire:target="search">ตรวจสอบการจอง ›</button>
        </form>
    </div>
</div>
