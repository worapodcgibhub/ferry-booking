<?php

namespace App\Livewire;

use App\Models\Island;
use App\Models\Port;
use App\Models\Route;
use App\Models\Trip;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class Home extends Component
{
    #[Url(as: 'island')]
    public ?string $islandSlug = null;

    public ?int $originId = null;
    public ?int $destinationId = null;
    public string $tripType = 'round';
    public ?string $departDate = null;
    public ?string $returnDate = null;
    public int $passengers = 2;

    public function mount(): void
    {
        $island = $this->island();
        $this->islandSlug = $island?->slug;
        $this->departDate ??= now()->addDays(7)->toDateString();
        $this->returnDate ??= now()->addDays(9)->toDateString();
        $this->syncRoute(null, null);
    }

    /** เกาะที่เลือกอยู่ (ถ้าไม่มีหรือปิดอยู่ ใช้เกาะแรกที่เปิดให้บริการ) */
    private function island(): ?Island
    {
        return Island::where('is_active', true)->where('slug', $this->islandSlug)->first()
            ?? Island::where('is_active', true)->orderBy('sort_order')->orderBy('id')->first();
    }

    private function routesFor(?Island $island): Collection
    {
        return $island
            ? Route::with(['origin', 'destination'])->where('is_active', true)->where('island_id', $island->id)->get()
            : collect();
    }

    /**
     * สลับเกาะ: เก็บวันที่ จำนวนคน และต้นทางเดิมไว้ ถ้ายังมีเส้นทางไปเกาะที่เลือก
     */
    public function selectIsland(string $slug): void
    {
        $this->islandSlug = $slug;
        $this->syncRoute($this->originId, $this->destinationId);
    }

    public function updatedOriginId(): void
    {
        $this->syncRoute($this->originId, $this->destinationId);
    }

    /** กลับทิศทาง ต้นทาง ⇄ ปลายทาง (เมื่อมีเส้นทางย้อนกลับ) */
    public function swap(): void
    {
        $exists = $this->routesFor($this->island())
            ->contains(fn ($r) => $r->origin_port_id === $this->destinationId && $r->destination_port_id === $this->originId);
        if ($exists) {
            [$this->originId, $this->destinationId] = [$this->destinationId, $this->originId];
        }
    }

    private function syncRoute(?int $keepOrigin, ?int $keepDestination): void
    {
        $routes = $this->routesFor($this->island());
        $origin = $keepOrigin && $routes->contains('origin_port_id', $keepOrigin)
            ? $keepOrigin
            : ($routes->first(fn ($r) => $r->origin->island_id === null) ?? $routes->first())?->origin_port_id;

        $dests = $routes->where('origin_port_id', $origin)->pluck('destination_port_id');
        $this->originId = $origin;
        $this->destinationId = $keepDestination && $dests->contains($keepDestination) ? $keepDestination : $dests->first();
    }

    public function search()
    {
        $this->validate([
            'originId' => 'required|integer',
            'destinationId' => 'required|integer|different:originId',
            'tripType' => 'required|in:oneway,round',
            'departDate' => 'required|date|after_or_equal:today',
            'returnDate' => 'exclude_unless:tripType,round|required|date|after_or_equal:departDate',
            'passengers' => 'required|integer|min:1|max:'.config('ferry.max_passengers'),
        ], [
            'departDate.after_or_equal' => 'เลือกวันเดินทางตั้งแต่วันนี้เป็นต้นไป',
            'returnDate.after_or_equal' => 'วันกลับต้องไม่ก่อนวันไป',
            'destinationId.different' => 'ต้นทางและปลายทางต้องไม่ใช่ท่าเดียวกัน',
        ]);

        $route = Route::where('origin_port_id', $this->originId)->where('destination_port_id', $this->destinationId)->where('is_active', true)->first();
        if (! $route) {
            $this->addError('destinationId', 'ยังไม่มีเส้นทางนี้');

            return;
        }
        if ($this->tripType === 'round' && ! $route->reverse()) {
            $this->addError('tripType', 'เส้นทางนี้ยังไม่มีเที่ยวกลับ เลือกเที่ยวเดียวแทน');

            return;
        }

        return $this->redirectRoute('book', array_filter([
            'origin' => $this->originId, 'dest' => $this->destinationId, 'type' => $this->tripType,
            'date' => $this->departDate, 'ret' => $this->tripType === 'round' ? $this->returnDate : null,
            'pax' => $this->passengers,
        ]));
    }

    public function render()
    {
        $island = $this->island();
        $routes = $this->routesFor($island);

        $originPorts = $routes->pluck('origin')->unique('id')->values();
        $destPorts = $routes->where('origin_port_id', $this->originId)->pluck('destination')->unique('id')->values();
        $route = $routes->first(fn ($r) => $r->origin_port_id === $this->originId && $r->destination_port_id === $this->destinationId);

        // ตัวอย่างตารางเรือ: รอบของเส้นทางที่เลือก ในวันที่เลือก (ถ้าวันนั้นไม่มี ใช้วันถัดไปที่มีรอบ)
        $trips = collect();
        $scheduleDate = null;
        if ($route) {
            $q = Trip::where('route_id', $route->id)->where('status', 'open')->where('departs_at', '>', now());
            $scheduleDate = (clone $q)->where('departs_at', '>=', $this->departDate ?: now()->toDateString())->min('departs_at');
            $scheduleDate = $scheduleDate ? substr((string) $scheduleDate, 0, 10) : null;
            if ($scheduleDate) {
                $trips = $q->whereDate('departs_at', $scheduleDate)->orderBy('departs_at')->get();
            }
        }

        $guidePorts = $routes->flatMap(fn ($r) => [$r->origin, $r->destination])->unique('id')->values();

        return view('livewire.home', [
            'island' => $island,
            'islands' => Island::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'originPorts' => $originPorts,
            'destPorts' => $destPorts,
            'route' => $route,
            'trips' => $trips,
            'scheduleDate' => $scheduleDate,
            'guidePorts' => $guidePorts,
        ])->layout('layouts::app', ['title' => ($island?->headline_text ?? 'จองเรือไปเกาะ').' — เรือไปเกาะ']);
    }
}
