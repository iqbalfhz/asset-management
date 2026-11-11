<?php

namespace App\Policies;

use App\Models\User;
use Filament\Actions\Exports\Models\Export;

class ExportPolicy
{
    /**
     * Rute unduh hasil export milik Filament berada di luar panel, jadi akun aktif dan sesi 2FA (bila aktif) dicek di sini.
     */
    public function view(User $user, Export $export): bool
    {
        return $export->user()->is($user)
            && $user->is_active
            && (! $user->hasConfirmedTwoFactor() || $user->hasValidTwoFactorSession());
    }
}
