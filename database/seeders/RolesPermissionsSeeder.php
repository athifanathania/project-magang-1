<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder; // ⬅️ PENTING, jangan hilang!
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            'berkas.view','berkas.create','berkas.update','berkas.delete',
            'lampiran.view','lampiran.create','lampiran.update','lampiran.delete',
        ];

        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // Hapus permission lama kalau dulu sempat ada
        Permission::where('name', 'berkas.finalize')->delete();

        $admin  = Role::firstOrCreate(['name' => 'Admin',  'guard_name' => 'web']);
        $editor = Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);
        $viewer = Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);

        $admin->syncPermissions(Permission::pluck('name'));

        $editor->syncPermissions([
            'berkas.view','berkas.create','berkas.update','berkas.delete',
            'lampiran.view','lampiran.create','lampiran.update','lampiran.delete',
        ]);

        $viewer->syncPermissions(['berkas.view']);
    }
}
