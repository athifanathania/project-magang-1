<?php

namespace App\Policies;

use App\Models\Lampiran;
use App\Models\User;

class LampiranPolicy
{
    public function viewAny(?User $u): bool { return true; }

    public function view(?User $u, Lampiran $r): bool
    {
        if ($r->berkas && $r->berkas->is_public) return true; // ikut Berkas
        return $u?->can('lampiran.view') ?? false;
    }

    public function create(User $u): bool { return $u->can('lampiran.create'); }
    public function update(User $u, Lampiran $r): bool { return $u->can('lampiran.update'); }
    public function delete(User $u, Lampiran $r): bool { return $u->can('lampiran.delete'); }
}
