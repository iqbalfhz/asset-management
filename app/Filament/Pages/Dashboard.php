<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Company;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(['md' => 3])
                    ->schema([
                        Select::make('perusahaan')
                            ->label(__('Perusahaan'))
                            ->placeholder(__('Semua perusahaan'))
                            ->options(fn (): array => $this->companyOptions())
                            ->visible(fn (): bool => count($this->companyOptions()) > 1),
                        Select::make('kategori')
                            ->label(__('Kategori'))
                            ->placeholder(__('Semua kategori'))
                            ->options(fn (): array => Category::query()->managedByCurrentUser()->orderBy('nama')->pluck('nama', 'id')->all())
                            ->searchable(),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function companyOptions(): array
    {
        return once(fn (): array => Company::query()->visibleToCurrentUser()->orderBy('nama')->pluck('nama', 'id')->all());
    }
}
