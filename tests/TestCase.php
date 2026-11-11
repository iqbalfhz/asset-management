<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        // Akun yang sudah punya 2FA dianggap sudah memasukkan kode, supaya test akses tidak tertahan di halaman 2FA.
        if ($user instanceof User && $user->breezySession) {
            $this->withSession(['breezy_session_id' => md5($user->breezySession->id)]);
        }

        return parent::actingAs($user, $guard);
    }
}
