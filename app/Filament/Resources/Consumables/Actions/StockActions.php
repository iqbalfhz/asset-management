<?php

namespace App\Filament\Resources\Consumables\Actions;

use App\Models\Consumable;
use App\Models\Employee;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use LogicException;

class StockActions
{
    public static function receive(): Action
    {
        return Action::make('receive')
            ->label(__('Masuk'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->authorize('update')
            ->modalHeading(fn (Consumable $record): string => __('Stok masuk: :nama', ['nama' => $record->nama]))
            ->schema([
                TextInput::make('jumlah')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(1_000_000)
                    ->suffix(fn (Consumable $record): string => $record->satuan)
                    ->required(),
                Select::make('supplier_id')
                    ->label(__('Supplier'))
                    ->options(fn (): array => Supplier::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->searchable(),
                DatePicker::make('tanggal')
                    ->default(today())
                    ->maxDate(today())
                    ->required(),
                Textarea::make('catatan')
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
            ->label(__('Keluar'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->authorize('update')
            ->disabled(fn (Consumable $record): bool => $record->stok === 0)
            ->tooltip(fn (Consumable $record): ?string => $record->stok === 0 ? __('Stok habis') : null)
            ->modalHeading(fn (Consumable $record): string => __('Stok keluar: :nama', ['nama' => $record->nama]))
            ->schema([
                TextInput::make('jumlah')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(fn (Consumable $record): int => $record->stok)
                    ->suffix(fn (Consumable $record): string => $record->satuan)
                    ->helperText(fn (Consumable $record): string => __('Stok sekarang :stok :satuan.', ['stok' => $record->stok, 'satuan' => $record->satuan]))
                    ->required(),
                Select::make('employee_id')
                    ->label(__('Penerima'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search, Consumable $record): array => Employee::query()
                        ->where('company_id', $record->company_id)
                        ->where('is_active', true)
                        ->where(fn (Builder $query) => $query->where('nama', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"))
                        ->orderBy('nama')
                        ->limit(50)
                        ->pluck('nama', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn (int|string $value): ?string => Employee::query()->whereKey($value)->value('nama'))
                    ->required(),
                DatePicker::make('tanggal')
                    ->default(today())
                    ->maxDate(today())
                    ->required(),
                Textarea::make('catatan')
                    ->maxLength(1000),
            ])
            // Penerima dicari ulang dengan hak lihat user, bukan dipercaya dari isian form.
            ->action(fn (Consumable $record, array $data, Action $action) => static::run(
                $action,
                fn () => $record->issue((int) $data['jumlah'], Employee::query()->findOrFail($data['employee_id']), Arr::only($data, ['tanggal', 'catatan'])),
                __('Stok keluar tercatat'),
            ));
    }

    private static function run(Action $action, callable $transaction, string $successTitle): void
    {
        try {
            $transaction();
        } catch (LogicException $exception) {
            Notification::make()->danger()->title(__('Aksi dibatalkan'))->body($exception->getMessage())->send();
            $action->halt();
        }

        Notification::make()->success()->title($successTitle)->send();
        // Riwayat transaksi di halaman detail ikut dimuat ulang.
        $action->getLivewire()->dispatch('stock-moved');
    }
}
