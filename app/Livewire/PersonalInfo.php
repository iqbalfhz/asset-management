<?php

namespace App\Livewire;

use Filament\Forms\Components\TextInput;
use Jeffgreco13\FilamentBreezy\Livewire\PersonalInfo as BreezyPersonalInfo;

class PersonalInfo extends BreezyPersonalInfo
{
    // Email boleh kosong di project ini, jadi tidak wajib diisi saat menyimpan profil.
    protected function getEmailComponent(): TextInput
    {
        return parent::getEmailComponent()->required(false);
    }
}
