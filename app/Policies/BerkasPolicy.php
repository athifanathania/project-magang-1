<?php

namespace App\Policies;

use App\Models\Berkas;
use App\Models\User;

class BerkasPolicy
{
    public function viewAny(?User $u): bool
    {
        // untuk listing publik (front site)
        return true;
    }

    public function view(?User $u, Berkas $r): bool
    {
        if ($r->is_public) return true;              // publik: siapa pun boleh
        return $u?->can('berkas.view') ?? false;     // internal: butuh role
    }

    public function create(User $u): bool { return $u->can('berkas.create'); }
    public function update(User $u, Berkas $r): bool { return $u->can('berkas.update'); }
    public function delete(User $u, Berkas $r): bool { return $u->can('berkas.delete'); }
}
