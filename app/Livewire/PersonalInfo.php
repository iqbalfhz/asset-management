<?php

namespace App\Livewire;

use App\Enums\DigestFrequency;
use App\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Jeffgreco13\FilamentBreezy\Livewire\PersonalInfo as BreezyPersonalInfo;

class PersonalInfo extends BreezyPersonalInfo
{
    public array $only = ['name', 'email', 'locale', 'digest_frequency'];

    protected function getProfileFormComponents(): array
    {
        return [
            ...parent::getProfileFormComponents(),
            Select::make('locale')
                ->label(__('Bahasa'))
                ->options(Language::class)
                ->required(),
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

    // Bahasa baru berlaku untuk seluruh halaman, jadi halaman dimuat ulang setelah bahasa diganti.
    public function submit(): void
    {
        $previousLocale = $this->user->locale;

        parent::submit();

        if ($this->user->locale !== $previousLocale) {
            $this->js('window.location.reload()');
        }
    }
}
