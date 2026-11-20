<x-layouts.guest title="เข้าสู่ระบบหลังบ้าน">
    <div class="mx-auto mt-10 max-w-sm px-4">
        <div class="card p-6">
            <h1 class="mb-4 text-2xl font-extrabold">เข้าสู่ระบบหลังบ้าน</h1>
            <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="email">อีเมล</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" class="field" required autofocus>
                    @error('email')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="password">รหัสผ่าน</label>
                    <input id="password" name="password" type="password" class="field" required>
                </div>
                <button class="btn-pink w-full">เข้าสู่ระบบ</button>
            </form>
            <p class="mt-4 rounded-lg bg-sea-50 p-3 text-xs text-slate-600">
                บัญชีสาธิต (ในเครื่องเท่านั้น):<br>
                เจ้าของระบบ <b>owner@demo.test</b> / <b>password</b><br>
                เจ้าหน้าที่ <b>staff@demo.test</b> / <b>password</b>
            </p>
        </div>
    </div>
</x-layouts.guest>
