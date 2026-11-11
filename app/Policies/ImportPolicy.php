<?php

namespace App\Policies;

use App\Models\User;
use Filament\Actions\Imports\Models\Import;

class ImportPolicy
{
    /**
     * Rute unduh CSV baris gagal milik Filament berada di luar panel, jadi akun aktif dan sesi 2FA dicek di sini.
     */
    public function view(User $user, Import $import): bool
    {
        return $import->user()->is($user)
            && $user->is_active
            && $user->hasValidTwoFactorSession();
    }
}
