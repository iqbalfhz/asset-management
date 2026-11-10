<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

class EditProfile extends BaseEditProfile
{
    // Email boleh kosong di project ini, jadi tidak wajib diisi saat menyimpan profil.
    protected function getEmailFormComponent(): Component
    {
        /** @var TextInput $email */
        $email = parent::getEmailFormComponent();

        return $email->required(false);
    }
}
