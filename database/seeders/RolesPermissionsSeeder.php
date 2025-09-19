<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            // Berkas
            'berkas.view','berkas.create','berkas.update','berkas.delete','berkas.download',

            // Lampiran
            'lampiran.view','lampiran.create','lampiran.update','lampiran.delete','lampiran.download',

            // IMM (4 tabel IMM: manual mutu, prosedur, instruksi standar, formulir)
            'imm.view','imm.create','imm.update','imm.delete','imm.download',
        ];

        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // Buat role
        $admin  = Role::firstOrCreate(['name' => 'Admin',  'guard_name' => 'web']);
        $editor = Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);
        $staff   = Role::firstOrCreate(['name' => 'Staff',   'guard_name' => 'web']);
        $viewer = Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);

        // Admin -> semua izin
        $admin->syncPermissions(Permission::pluck('name'));

        // Editor -> bisa manage data (kecuali user)
        $editor->syncPermissions([
            'berkas.view','berkas.create','berkas.update','berkas.delete',
            'lampiran.view','lampiran.create','lampiran.update','lampiran.delete',
            'imm.view','imm.create','imm.update','imm.delete',
            'berkas.download','lampiran.download','imm.download',
        ]);

        // Staf -> hanya view + download
        $staff->syncPermissions([
            'berkas.view','lampiran.view','imm.view',
            'berkas.download','lampiran.download','imm.download',
        ]);

        // Viewer -> hanya view saja
        $viewer->syncPermissions([
            'berkas.view','lampiran.view','imm.view',
        ]);
    }
}
