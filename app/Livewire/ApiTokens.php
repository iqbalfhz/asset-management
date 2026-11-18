<?php

namespace App\Livewire;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Component;
use Jeffgreco13\FilamentBreezy\Livewire\SanctumTokens;

class ApiTokens extends SanctumTokens
{
    // Token API wajib punya tanggal kedaluwarsa, paling lama satu tahun.
    protected function getSanctumFormSchema(bool $edit = false): array
    {
        return array_map(fn (Component $field): Component => $field instanceof DatePicker
            ? $field->required()->minDate(today()->addDay())->maxDate(today()->addYear())
            : $field, parent::getSanctumFormSchema($edit));
    }
}
