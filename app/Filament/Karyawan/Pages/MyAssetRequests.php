<?php

namespace App\Filament\Karyawan\Pages;

use App\Filament\Actions\DomainAction;
use App\Filament\Karyawan\EmployeeTablePage;
use App\Models\AssetRequest;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyAssetRequests extends EmployeeTablePage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'permintaan';

    public static function getNavigationLabel(): string
    {
        return __('Permintaan saya');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->requestAction(),
        ];
    }

    public function table(Table $table): Table
    {
        $employee = auth()->user()->employee;

        return $this->emptyState($table, __('Belum ada permintaan'))
            ->query(fn (): Builder => AssetRequest::withoutGlobalScopes()
                ->with('category')
                ->where('employee_id', $employee?->getKey() ?? 0))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Diajukan'))
                    ->dateTime('j M Y H:i'),
                TextColumn::make('category.nama')
                    ->label(__('Kategori')),
                TextColumn::make('alasan')
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('catatan')
                    ->label(__('Catatan admin'))
                    ->placeholder('—')
                    ->wrap(),
            ]);
    }

    // Akun yang belum terhubung ke data karyawan tidak mendapat tombol; tabelnya menjelaskan sebabnya.
    private function requestAction(): Action
    {
        return Action::make('request')
            ->label(__('Ajukan permintaan'))
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (): bool => auth()->user()->employee !== null)
            ->modalDescription(__('Pilih jenis aset yang Anda butuhkan. Admin pengelolanya akan memutuskan, lalu menyerahkan asetnya.'))
            ->schema([
                Select::make('category_id')
                    ->label(__('Kategori'))
                    ->options(fn (): array => Category::query()->requestableIn(auth()->user()->employee->company_id)->orderBy('nama')->pluck('nama', 'id')->all())
                    ->required(),
                Textarea::make('alasan')
                    ->label(__('Alasan'))
                    ->placeholder(__('Contoh: laptop lama sering mati, dibutuhkan untuk presentasi ke klien.'))
                    ->rows(3)
                    ->required()
                    ->maxLength(2000),
            ])
            ->modalSubmitActionLabel(__('Kirim permintaan'))
            ->action(fn (array $data, Action $action) => DomainAction::run(
                $action,
                fn () => AssetRequest::submit(auth()->user()->employee, Category::query()->findOrFail($data['category_id']), $data['alasan']),
                __('Permintaan terkirim'),
                __('Permintaan belum terkirim'),
            ));
    }
}
