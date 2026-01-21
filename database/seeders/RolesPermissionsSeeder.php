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
            'berkas.view','berkas.create','berkas.update','berkas.delete','berkas.download',
            'lampiran.view','lampiran.create','lampiran.update','lampiran.delete','lampiran.download',
            'imm.view','imm.create','imm.update','imm.delete','imm.download',
            'regular.view','regular.create','regular.update','regular.delete','regular.download',
        ];

        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $admin  = Role::firstOrCreate(['name' => 'Admin',  'guard_name' => 'web']);
        $editor = Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);
        $staff  = Role::firstOrCreate(['name' => 'Staff',   'guard_name' => 'web']);
        $viewer = Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);

        $admin->syncPermissions(Permission::pluck('name'));

        $editor->syncPermissions([
            'berkas.view','berkas.create','berkas.update','berkas.delete',
            'lampiran.view','lampiran.create','lampiran.update','lampiran.delete',
            'imm.view','imm.create','imm.update','imm.delete',
            
            'regular.view','regular.create','regular.update','regular.delete',

            'berkas.download','lampiran.download','imm.download','regular.download',
        ]);

        $staff->syncPermissions([
            'berkas.view','lampiran.view','imm.view',
            'regular.view',
            
            'berkas.download','lampiran.download','imm.download',
            'regular.download',
        ]);

        $viewer->syncPermissions([
            'berkas.view','lampiran.view','imm.view',
            'regular.view',
        ]);
    }
}