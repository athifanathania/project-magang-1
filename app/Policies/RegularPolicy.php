<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Regular;

class RegularPolicy
{
    // boleh listing (opsional)
    public function viewAny(?User $user): bool
    {
        return true;
    }

    // KUNCI: izinkan lihat file regular
    public function view(?User $user, Regular $regular): bool
    {
        // Kalau kamu punya kolom is_public di Regular, bisa begini:
        // if ($regular->is_public) return true;

        // Internal: wajib login + punya salah satu role ini
        return $user?->hasAnyRole(['Admin','Editor','Staff']) ?? false;
    }

    // (opsional) CRUD lain kalau perlu
    public function create(User $user): bool { return $user->can('regular.create'); }
    public function update(User $user, Regular $r): bool { return $user->can('regular.update'); }
    public function delete(User $user, Regular $r): bool { return $user->can('regular.delete'); }
}
