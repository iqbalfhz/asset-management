<?php

namespace App\Livewire;

use App\Enums\DigestFrequency;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Jeffgreco13\FilamentBreezy\Livewire\PersonalInfo as BreezyPersonalInfo;

class PersonalInfo extends BreezyPersonalInfo
{
    public array $only = ['name', 'email', 'digest_frequency'];

    protected function getProfileFormComponents(): array
    {
        return [
            ...parent::getProfileFormComponents(),
            Select::make('digest_frequency')
                ->label(__('Ringkasan email'))
                ->helperText(__('Daftar hal yang perlu ditindaklanjuti, dikirim jam 07.00. Butuh email yang terisi.'))
                ->options(DigestFrequency::class)
                ->required(),
        ];
    }

    // Email boleh kosong di project ini, jadi tidak wajib diisi saat menyimpan profil.
    protected function getEmailComponent(): TextInput
    {
        return parent::getEmailComponent()->required(false);
    }
}
