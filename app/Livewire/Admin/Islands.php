<?php

namespace App\Livewire\Admin;

use App\Models\Area;
use App\Models\Island;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class Islands extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;
    public bool $showForm = false;
    public string $name = '';
    public string $headline = '';
    public string $description = '';
    public ?int $areaId = null;
    public int $sortOrder = 0;
    public bool $isActive = true;
    public $cover = null;
    public ?string $currentCover = null;
    public string $newArea = '';
    public ?string $flash = null;

    public function addArea(): void
    {
        $this->validate(['newArea' => 'required|string|max:100'], ['newArea.required' => 'กรุณากรอกชื่อพื้นที่']);
        $area = Area::firstOrCreate(['slug' => $this->slugFor($this->newArea, Area::class)], ['name' => trim($this->newArea)]);
        $this->areaId = $area->id;
        $this->newArea = '';
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $i = Island::findOrFail($id);
        $this->editingId = $i->id;
        $this->name = $i->name;
        $this->headline = (string) $i->headline;
        $this->description = (string) $i->description;
        $this->areaId = $i->area_id;
        $this->sortOrder = $i->sort_order;
        $this->isActive = $i->is_active;
        $this->currentCover = $i->cover_url;
        $this->cover = null;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:100',
            'headline' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'areaId' => 'nullable|exists:areas,id',
            'sortOrder' => 'integer|min:0|max:999',
            'cover' => ['nullable', 'image', 'max:5120'],
        ], [
            'name.required' => 'กรุณากรอกชื่อเกาะ',
            'cover.image' => 'รูปปกต้องเป็นไฟล์ภาพ',
            'cover.max' => 'รูปปกต้องไม่เกิน 5 MB',
        ]);

        $island = $this->editingId ? Island::findOrFail($this->editingId) : new Island;
        $island->fill([
            'name' => trim($this->name),
            'headline' => trim($this->headline) ?: null,
            'description' => trim($this->description) ?: null,
            'area_id' => $this->areaId,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ]);
        if (! $island->exists) {
            $island->slug = $this->slugFor($this->name, Island::class);
        }
        if ($this->cover) {
            $island->cover_path = $this->cover->store('covers', 'public');
        }
        $island->save();

        $this->flash = 'บันทึกเกาะ '.$island->name.' แล้ว';
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'showForm', 'name', 'headline', 'description', 'areaId', 'sortOrder', 'cover', 'currentCover');
        $this->isActive = true;
        $this->resetValidation();
    }

    private function slugFor(string $name, string $model): string
    {
        $base = Str::slug($name) ?: 'item-'.Str::lower(Str::random(5));
        $slug = $base;
        for ($n = 2; $model::where('slug', $slug)->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.admin.islands', [
            'islands' => Island::with('area')->orderBy('sort_order')->orderBy('id')->get(),
            'areas' => Area::orderBy('name')->get(),
        ])->layout('layouts::admin', ['title' => 'เกาะและพื้นที่']);
    }
}
