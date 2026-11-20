<?php

namespace App\Livewire\Admin;

use App\Models\BookingLeg;
use App\Models\Route;
use App\Models\Trip;
use App\Services\TripGenerator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Trips extends Component
{
    use WithPagination;

    // ---- สร้างเป็นชุด ----
    public ?int $routeId = null;
    public ?string $fromDate = null;
    public ?string $toDate = null;
    public string $times = '08:00, 11:00, 14:00';
    public array $weekdays = [];
    public int $price = 450;
    public int $capacity = 40;
    public string $provider = 'เรือไปเกาะ';

    // ---- กรองรายการ ----
    #[Url] public ?int $filterRoute = null;
    #[Url] public ?string $filterDate = null;

    // ---- แก้รายเที่ยว ----
    public ?int $editingId = null;
    public string $editDeparts = '';
    public int $editPrice = 0;
    public int $editCapacity = 0;
    public string $editStatus = 'open';

    public ?string $flash = null;
    public ?string $error = null;

    public function mount(): void
    {
        $this->fromDate = now()->addDay()->toDateString();
        $this->toDate = now()->addDays(14)->toDateString();
        $this->filterDate ??= now()->toDateString();
    }

    public function updatingFilterRoute(): void { $this->resetPage(); }
    public function updatingFilterDate(): void { $this->resetPage(); }

    public function generate(): void
    {
        $this->flash = $this->error = null;
        $this->validate([
            'routeId' => 'required|exists:routes,id',
            'fromDate' => 'required|date',
            'toDate' => 'required|date|after_or_equal:fromDate',
            'times' => 'required|string',
            'price' => 'required|integer|min:0|max:100000',
            'capacity' => 'required|integer|min:1|max:1000',
            'provider' => 'required|string|max:100',
        ], [
            'routeId.required' => 'เลือกเส้นทาง', 'toDate.after_or_equal' => 'วันสิ้นสุดต้องไม่ก่อนวันเริ่ม',
        ]);

        $times = collect(preg_split('/[\s,]+/', trim($this->times), -1, PREG_SPLIT_NO_EMPTY))->unique()->values();
        if ($times->isEmpty() || $times->contains(fn ($t) => ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t))) {
            $this->addError('times', 'ใส่เวลารูปแบบ 08:00 คั่นด้วยจุลภาค เช่น 08:00, 11:00');

            return;
        }
        if (Carbon::parse($this->fromDate)->diffInDays($this->toDate) > 180) {
            $this->addError('toDate', 'สร้างครั้งละไม่เกิน 180 วัน');

            return;
        }

        $n = (new TripGenerator)->generate(
            Route::findOrFail($this->routeId), Carbon::parse($this->fromDate), Carbon::parse($this->toDate),
            $times->all(), $this->price, $this->capacity, array_map('intval', $this->weekdays), $this->provider,
        );

        $this->flash = "สร้างรอบเรือใหม่ {$n} รอบ (ข้ามรอบที่มีอยู่แล้ว)";
    }

    public function edit(int $id): void
    {
        $t = Trip::findOrFail($id);
        $this->editingId = $id;
        $this->editDeparts = $t->departs_at->format('Y-m-d\TH:i');
        $this->editPrice = $t->price;
        $this->editCapacity = $t->capacity;
        $this->editStatus = $t->status;
        $this->error = $this->flash = null;
    }

    public function saveEdit(): void
    {
        $t = Trip::with('route')->findOrFail($this->editingId);
        $this->validate([
            'editDeparts' => 'required|date', 'editPrice' => 'required|integer|min:0|max:100000',
            'editCapacity' => 'required|integer|min:1|max:1000', 'editStatus' => 'required|in:open,closed',
        ]);

        if ($this->editCapacity < $t->seats_taken) {
            $this->error = "โควตาต้องไม่น้อยกว่าที่นั่งที่ถูกจองแล้ว ({$t->seats_taken})";

            return;
        }
        $departs = Carbon::parse($this->editDeparts);
        $t->update([
            'departs_at' => $departs, 'arrives_at' => $departs->copy()->addMinutes($t->route->duration_minutes),
            'price' => $this->editPrice, 'capacity' => $this->editCapacity, 'status' => $this->editStatus,
        ]);
        $this->editingId = null;
        $this->flash = 'บันทึกรอบเรือแล้ว (ราคาใหม่มีผลกับการจองใหม่เท่านั้น)';
    }

    public function deleteTrip(int $id): void
    {
        $t = Trip::findOrFail($id);
        if ($t->seats_taken > 0 || BookingLeg::where('trip_id', $id)->exists()) {
            $this->error = 'ลบไม่ได้: รอบนี้มีการจองแล้ว ให้ปิดจองแทน';

            return;
        }
        $t->delete();
        $this->flash = 'ลบรอบเรือแล้ว';
    }

    public function render()
    {
        $trips = Trip::with('route.origin', 'route.destination')
            ->when($this->filterRoute, fn ($q) => $q->where('route_id', $this->filterRoute))
            ->when($this->filterDate, fn ($q) => $q->whereDate('departs_at', '>=', $this->filterDate))
            ->orderBy('departs_at')->paginate(20);

        return view('livewire.admin.trips', [
            'trips' => $trips,
            'routes' => Route::with('origin', 'destination')->where('is_active', true)->orderBy('id')->get(),
        ])->layout('layouts::admin', ['title' => 'รอบเรือ']);
    }
}
