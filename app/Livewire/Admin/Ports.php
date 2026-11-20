<?php

namespace App\Livewire\Admin;

use App\Models\Area;
use App\Models\Island;
use App\Models\Port;
use Livewire\Component;

class Ports extends Component
{
    public ?int $editingId = null;
    public bool $showForm = false;
    public string $name = '';
    public string $location = '';
    public string $boardingPoint = '';
    public string $note = '';
    public string $mapUrl = '';
    public ?int $areaId = null;
    public ?int $islandId = null;
    public bool $isActive = true;
    public ?string $flash = null;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $p = Port::findOrFail($id);
        $this->editingId = $p->id;
        $this->name = $p->name;
        $this->location = (string) $p->location;
        $this->boardingPoint = (string) $p->boarding_point;
        $this->note = (string) $p->note;
        $this->mapUrl = (string) $p->map_url;
        $this->areaId = $p->area_id;
        $this->islandId = $p->island_id;
        $this->isActive = $p->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:120',
            'location' => 'nullable|string|max:190',
            'boardingPoint' => 'nullable|string|max:190',
            'note' => 'nullable|string|max:500',
            'mapUrl' => 'nullable|url|max:500',
            'areaId' => 'nullable|exists:areas,id',
            'islandId' => 'nullable|exists:islands,id',
        ], ['name.required' => 'กรุณากรอกชื่อท่าเรือ', 'mapUrl.url' => 'ลิงก์แผนที่ต้องขึ้นต้นด้วย http:// หรือ https://']);

        Port::updateOrCreate(['id' => $this->editingId], [
            'name' => trim($this->name),
            'location' => trim($this->location) ?: null,
            'boarding_point' => trim($this->boardingPoint) ?: null,
            'note' => trim($this->note) ?: null,
            'map_url' => trim($this->mapUrl) ?: null,
            'area_id' => $this->areaId,
            'island_id' => $this->islandId,
            'is_active' => $this->isActive,
        ]);

        $this->flash = 'บันทึกท่าเรือแล้ว';
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'showForm', 'name', 'location', 'boardingPoint', 'note', 'mapUrl', 'areaId', 'islandId');
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.ports', [
            'ports' => Port::with('island', 'area')->orderBy('name')->get(),
            'areas' => Area::orderBy('name')->get(),
            'islands' => Island::orderBy('name')->get(),
        ])->layout('layouts::admin', ['title' => 'ท่าเรือและจุดขึ้นเรือ']);
    }
}
