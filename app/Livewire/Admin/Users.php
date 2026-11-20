<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Users extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'staff';
    public ?string $flash = null;
    public ?string $error = null;

    public function create(): void
    {
        $this->flash = $this->error = null;
        $this->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['owner', 'staff'])],
        ], ['password.min' => 'รหัสผ่านอย่างน้อย 8 ตัวอักษร', 'email.unique' => 'อีเมลนี้ถูกใช้แล้ว']);

        User::create(['name' => $this->name, 'email' => $this->email, 'password' => $this->password, 'role' => $this->role]);
        $this->reset('name', 'email', 'password');
        $this->role = 'staff';
        $this->flash = 'เพิ่มผู้ใช้งานแล้ว';
    }

    public function setRole(int $id, string $role): void
    {
        $this->flash = $this->error = null;
        abort_unless(in_array($role, ['owner', 'staff'], true), 422);
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            $this->error = 'เปลี่ยนบทบาทของตัวเองไม่ได้';

            return;
        }
        $user->update(['role' => $role]);
        $this->flash = 'เปลี่ยนบทบาทแล้ว';
    }

    public function remove(int $id): void
    {
        $this->flash = $this->error = null;
        if ($id === auth()->id()) {
            $this->error = 'ลบบัญชีตัวเองไม่ได้';

            return;
        }
        User::findOrFail($id)->delete();
        $this->flash = 'ลบผู้ใช้งานแล้ว';
    }

    public function render()
    {
        return view('livewire.admin.users', ['users' => User::orderBy('id')->get()])
            ->layout('layouts::admin', ['title' => 'ผู้ใช้งาน']);
    }
}
