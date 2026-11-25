@props([
    'text',                 // ข้อความที่จะคัดลอก
    'label' => 'คัดลอก',    // ข้อความบนปุ่ม
    'rows' => 1,            // ความสูงช่องข้อความ (บรรทัด)
    'visible' => false,     // true = แสดงช่องข้อความเสมอ, false = แสดงเฉพาะเมื่อคัดลอกอัตโนมัติไม่ได้
    'btnClass' => 'btn-pink',
])

{{--
    ตัวช่วยคัดลอกกลาง (ใช้ร่วมกันทุกปุ่มคัดลอกในแอป)
    navigator.clipboard ใช้ได้เฉพาะ HTTPS/localhost (http://xxx.test ไม่ใช่) จึงสำรองด้วย textarea ชั่วคราว + execCommand('copy')
    ถ้าคัดลอกไม่สำเร็จจริง จะแสดงช่องข้อความที่เลือกไว้แล้ว พร้อมข้อความให้กด Ctrl+C เอง (ไม่เงียบหาย)
--}}
<div {{ $attributes }} x-data="{
    copied: false,
    failed: false,
    async copy() {
        const text = this.$refs.t.value;
        this.failed = false;
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                const tmp = document.createElement('textarea');
                tmp.value = text;
                tmp.setAttribute('readonly', '');
                tmp.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
                document.body.appendChild(tmp);
                tmp.focus(); tmp.select(); tmp.setSelectionRange(0, text.length);
                let ok = false;
                try { ok = document.execCommand('copy'); } finally { tmp.remove(); }
                if (!ok) throw new Error('copy failed');
            }
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        } catch (e) {
            this.failed = true;
            this.$nextTick(() => { this.$refs.t.focus(); this.$refs.t.select(); });
        }
    }
}">
    <textarea x-ref="t" readonly rows="{{ $rows }}" data-copy-source @unless ($visible) x-show="failed" x-cloak @endunless
        class="field mb-2 font-mono text-sm">{{ $text }}</textarea>
    <button type="button" class="{{ $btnClass }}" @click="copy()">
        <span x-show="!copied">{{ $label }}</span><span x-show="copied" x-cloak>คัดลอกแล้ว ✓</span>
    </button>
    <p x-show="failed" x-cloak class="err" role="alert">คัดลอกอัตโนมัติไม่ได้ ข้อความถูกเลือกไว้แล้ว กด Ctrl+C เพื่อคัดลอกเอง</p>
</div>
