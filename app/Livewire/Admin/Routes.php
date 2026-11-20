<?php

namespace App\Livewire\Admin;

use App\Models\Island;
use App\Models\Port;
use App\Models\Route;
use Livewire\Component;

class Routes extends Component
{
    public ?int $editingId = null;
    public bool $showForm = false;
    public ?int $originId = null;
    public ?int $destinationId = null;
    public ?int $islandId = null;
    public int $duration = 60;
    public bool $isActive = true;
    public bool $createReverse = true;
    public ?string $flash = null;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $r = Route::findOrFail($id);
        $this->editingId = $r->id;
        $this->originId = $r->origin_port_id;
        $this->destinationId = $r->destination_port_id;
        $this->islandId = $r->island_id;
        $this->duration = $r->duration_minutes;
        $this->isActive = $r->is_active;
        $this->createReverse = false;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'originId' => 'required|exists:ports,id',
            'destinationId' => 'required|exists:ports,id|different:originId',
            'islandId' => 'required|exists:islands,id',
            'duration' => 'required|integer|min:5|max:1440',
        ], [
            'originId.required' => 'เลือกท่าต้นทาง', 'destinationId.required' => 'เลือกท่าปลายทาง',
            'destinationId.different' => 'ท่าต้นทางและปลายทางต้องไม่ซ้ำกัน', 'islandId.required' => 'เลือกเกาะที่เส้นทางนี้ให้บริการ',
        ]);

        $dup = Route::where('origin_port_id', $this->originId)->where('destination_port_id', $this->destinationId)
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))->exists();
        if ($dup) {
            $this->addError('destinationId', 'มีเส้นทางนี้อยู่แล้ว');

            return;
        }

        Route::updateOrCreate(['id' => $this->editingId], [
            'origin_port_id' => $this->originId, 'destination_port_id' => $this->destinationId,
            'island_id' => $this->islandId, 'duration_minutes' => $this->duration, 'is_active' => $this->isActive,
        ]);

        if (! $this->editingId && $this->createReverse) {
            Route::firstOrCreate(
                ['origin_port_id' => $this->destinationId, 'destination_port_id' => $this->originId],
                ['island_id' => $this->islandId, 'duration_minutes' => $this->duration, 'is_active' => $this->isActive],
            );
        }

        $this->flash = 'บันทึกเส้นทางแล้ว';
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'showForm', 'originId', 'destinationId', 'islandId');
        $this->duration = 60;
        $this->isActive = true;
        $this->createReverse = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.routes', [
            'routes' => Route::with('origin', 'destination', 'island')->orderBy('island_id')->orderBy('id')->get(),
            'ports' => Port::orderBy('name')->get(),
            'islands' => Island::orderBy('name')->get(),
        ])->layout('layouts::admin', ['title' => 'เส้นทาง']);
    }
}
