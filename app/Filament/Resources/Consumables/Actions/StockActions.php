<?php

namespace App\Filament\Resources\Consumables\Actions;

use App\Filament\Actions\DomainAction;
use App\Filament\Schemas\Components\EmployeeSelect;
use App\Models\Consumable;
use App\Models\Employee;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

class StockActions
{
    public static function receive(): Action
    {
        return Action::make('receive')
            ->label(__('Stok masuk'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->authorize('update')
            ->modalHeading(fn (Consumable $record): string => __('Stok masuk: :nama', ['nama' => $record->nama]))
            ->schema([
                TextInput::make('jumlah')
                    ->label(__('Jumlah'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(1_000_000)
                    ->suffix(fn (Consumable $record): string => $record->satuan)
                    ->required(),
                Select::make('supplier_id')
                    ->label(__('Supplier'))
                    ->options(fn (): array => Supplier::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->searchable(),
                static::dateInput(),
                Textarea::make('catatan')
                    ->label(__('Catatan'))
                    ->maxLength(1000),
            ])
            ->action(fn (Consumable $record, array $data, Action $action) => static::run(
                $action,
                fn () => $record->receive((int) $data['jumlah'], Arr::only($data, ['supplier_id', 'tanggal', 'catatan'])),
                __('Stok masuk tercatat'),
            ));
    }

    public static function issue(): Action
    {
        return Action::make('issue')
            ->label(__('Stok keluar'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->authorize('update')
            ->disabled(fn (Consumable $record): bool => $record->stok === 0)
            ->tooltip(fn (Consumable $record): ?string => $record->stok === 0 ? __('Stok habis') : null)
            ->modalHeading(fn (Consumable $record): string => __('Stok keluar: :nama', ['nama' => $record->nama]))
            ->schema([
                TextInput::make('jumlah')
                    ->label(__('Jumlah'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(fn (Consumable $record): int => $record->stok)
                    ->suffix(fn (Consumable $record): string => $record->satuan)
                    ->helperText(fn (Consumable $record): string => __('Stok sekarang :stok :satuan.', ['stok' => $record->stok, 'satuan' => $record->satuan]))
                    ->required(),
                EmployeeSelect::make(__('Penerima'), fn (Consumable $record): int => $record->company_id),
                static::dateInput(),
                Textarea::make('catatan')
                    ->label(__('Catatan'))
                    ->maxLength(1000),
            ])
            // Penerima dicari ulang dengan hak lihat user, bukan dipercaya dari isian form.
            ->action(fn (Consumable $record, array $data, Action $action) => static::run(
                $action,
                fn () => $record->issue((int) $data['jumlah'], Employee::query()->findOrFail($data['employee_id']), Arr::only($data, ['tanggal', 'catatan'])),
                __('Stok keluar tercatat'),
            ));
    }

    public static function adjust(): Action
    {
        return Action::make('adjust')
            ->label(__('Penyesuaian'))
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->authorize('update')
            ->modalHeading(fn (Consumable $record): string => __('Penyesuaian stok: :nama', ['nama' => $record->nama]))
            ->modalDescription(__('Untuk memperbaiki salah catat atau menyamakan dengan hasil hitung fisik. Selisihnya dicatat di riwayat.'))
            ->schema([
                TextInput::make('stok_sebenarnya')
                    ->label(__('Stok sebenarnya'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(1_000_000)
                    ->suffix(fn (Consumable $record): string => $record->satuan)
                    ->helperText(fn (Consumable $record): string => __('Stok tercatat sekarang :stok :satuan.', ['stok' => $record->stok, 'satuan' => $record->satuan]))
                    ->required(),
                static::dateInput(),
                Textarea::make('alasan')
                    ->label(__('Alasan'))
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(fn (Consumable $record, array $data, Action $action) => static::run(
                $action,
                fn () => $record->adjust((int) $data['stok_sebenarnya'], $data['alasan'], Arr::only($data, ['tanggal'])),
                __('Penyesuaian stok tercatat'),
            ));
    }

    private static function dateInput(): DatePicker
    {
        return DatePicker::make('tanggal')
            ->label(__('Tanggal'))
            ->default(today())
            ->maxDate(today())
            ->required();
    }

    private static function run(Action $action, callable $transaction, string $successTitle): void
    {
        DomainAction::run($action, $transaction, $successTitle);
        // Riwayat transaksi di halaman detail ikut dimuat ulang.
        $action->getLivewire()->dispatch('stock-moved');
    }
}
