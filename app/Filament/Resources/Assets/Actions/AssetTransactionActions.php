<?php

namespace App\Filament\Resources\Assets\Actions;

use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Room;
use App\Models\Status;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use LogicException;

// Serah-terima dan pengembalian, dipakai di tabel aset (satuan dan massal) dan di halaman edit aset.
class AssetTransactionActions
{
    public static function handOver(): Action
    {
        return static::handOverForm(Action::make('handOver'))
            ->visible(fn (Asset $record): bool => $record->status->nama === Status::TERSEDIA)
            ->authorize('update')
            ->action(fn (Asset $record, array $data, Action $action) => static::runHandOver(new Collection([$record]), $data, $action))
            ->after(fn (Component $livewire) => static::refreshEditPage($livewire));
    }

    public static function handOverBulk(): BulkAction
    {
        return static::handOverForm(BulkAction::make('handOverBulk'))
            ->visible(fn (): bool => auth()->user()?->can('Update:Asset') ?? false)
            ->authorizeIndividualRecords('update')
            ->action(fn (Collection $records, array $data, BulkAction $action) => static::runHandOver($records, $data, $action))
            ->deselectRecordsAfterCompletion();
    }

    public static function takeBack(): Action
    {
        return static::takeBackForm(Action::make('takeBack'))
            ->visible(fn (Asset $record): bool => $record->status->nama === Status::DIPAKAI)
            ->authorize('update')
            ->action(fn (Asset $record, array $data, Action $action) => static::runTakeBack(new Collection([$record]), $data, $action))
            ->after(fn (Component $livewire) => static::refreshEditPage($livewire));
    }

    public static function takeBackBulk(): BulkAction
    {
        return static::takeBackForm(BulkAction::make('takeBackBulk'))
            ->visible(fn (): bool => auth()->user()?->can('Update:Asset') ?? false)
            ->authorizeIndividualRecords('update')
            ->action(fn (Collection $records, array $data, BulkAction $action) => static::runTakeBack($records, $data, $action))
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    private static function handOverForm(Action $action): Action
    {
        return $action
            ->label(__('Serahkan'))
            ->icon(Heroicon::OutlinedArrowUpRight)
            ->modalHeading(__('Serah-terima aset'))
            ->modalSubmitActionLabel(__('Serahkan'))
            ->schema([
                Select::make('target_type')
                    ->label(__('Diserahkan ke'))
                    ->options([
                        Employee::class => __('Karyawan'),
                        Room::class => __('Ruang'),
                        Department::class => __('Departemen'),
                        Asset::class => __('Aset lain'),
                    ])
                    ->default(Employee::class)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('target_id', null)),
                Select::make('target_id')
                    ->label(__('Tujuan'))
                    ->helperText(__('Ketik nama, NIK, atau kode untuk mencari.'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => static::searchTargets($get('target_type'), $search))
                    ->getOptionLabelUsing(function (mixed $value, Get $get): ?string {
                        $target = static::targetQuery($get('target_type'))?->find($value);

                        return $target ? Assignment::labelFor($target) : null;
                    })
                    ->required(),
                DatePicker::make('tanggal')
                    ->label(__('Tanggal'))
                    ->default(now())
                    ->maxDate(now())
                    ->required(),
                static::conditionInput(),
                DatePicker::make('expected_return_at')
                    ->label(__('Tanggal kembali'))
                    ->helperText(__('Isi kalau aset dipinjamkan.'))
                    ->afterOrEqual('tanggal'),
                Textarea::make('catatan')
                    ->label(__('Catatan'))
                    ->rows(2)
                    ->maxLength(1000),
            ]);
    }

    /**
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    private static function takeBackForm(Action $action): Action
    {
        return $action
            ->label(__('Kembalikan'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->modalHeading(__('Pengembalian aset'))
            ->modalDescription(__('Aset kembali ke lokasi default. Aset yang menempel ikut kembali.'))
            ->modalSubmitActionLabel(__('Kembalikan'))
            ->schema([
                DatePicker::make('tanggal')
                    ->label(__('Tanggal'))
                    ->default(now())
                    ->maxDate(now())
                    ->required(),
                static::conditionInput(),
                Textarea::make('catatan')
                    ->label(__('Catatan'))
                    ->rows(2)
                    ->maxLength(1000),
            ]);
    }

    private static function conditionInput(): TextInput
    {
        return TextInput::make('kondisi')
            ->label(__('Kondisi'))
            ->datalist([__('Baik'), __('Lecet ringan'), __('Ada kekurangan')])
            ->default(__('Baik'))
            ->required()
            ->maxLength(255);
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @param  array<string, mixed>  $data
     */
    private static function runHandOver(Collection $assets, array $data, Action $action): void
    {
        $target = static::targetQuery($data['target_type'])?->find($data['target_id']);

        try {
            if ($target === null) {
                throw new LogicException(__('Tujuan serah-terima tidak ditemukan.'));
            }

            $noBast = Assignment::handOver($assets, $target, $data);
        } catch (LogicException $exception) {
            Notification::make()->danger()->title(__('Serah-terima dibatalkan'))->body($exception->getMessage())->send();
            $action->halt();
        }

        static::notifySaved(__('Serah-terima tersimpan'), $noBast);
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @param  array<string, mixed>  $data
     */
    private static function runTakeBack(Collection $assets, array $data, Action $action): void
    {
        try {
            $noBast = Assignment::takeBack($assets, $data);
        } catch (LogicException $exception) {
            Notification::make()->danger()->title(__('Pengembalian dibatalkan'))->body($exception->getMessage())->send();
            $action->halt();
        }

        static::notifySaved(__('Pengembalian tersimpan'), $noBast);
    }

    private static function notifySaved(string $title, string $noBast): void
    {
        $assignmentId = Assignment::query()->where('no_bast', $noBast)->value('id');

        Notification::make()
            ->success()
            ->title($title)
            ->body(__('Nomor BAST: :nomor', ['nomor' => $noBast]))
            ->actions([
                Action::make('bast')
                    ->label(__('Unduh BAST'))
                    ->url(route('filament.admin.bast.download', $assignmentId))
                    ->openUrlInNewTab(),
            ])
            ->send();
    }

    /**
     * Daftar tujuan sesuai jenisnya; karyawan nonaktif tidak bisa dipilih.
     *
     * @return Builder<Model>|null
     */
    private static function targetQuery(?string $type): ?Builder
    {
        return match ($type) {
            Employee::class => Employee::query()->where('is_active', true),
            Room::class => Room::query()->with('floor.building'),
            Department::class => Department::query(),
            Asset::class => Asset::query(),
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private static function searchTargets(?string $type, string $search): array
    {
        $like = "%{$search}%";

        return static::targetQuery($type)
            ?->where(fn (Builder $query) => match ($type) {
                Employee::class => $query->where('nama', 'like', $like)->orWhere('nik', 'like', $like),
                Asset::class => $query->where('kode', 'like', $like)->orWhere('nama', 'like', $like),
                default => $query->where('nama', 'like', $like),
            })
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Model $target): array => [$target->getKey() => Assignment::labelFor($target)])
            ->all() ?? [];
    }

    // Halaman lihat dan edit memegang salinan aset sendiri; muat ulang supaya status dan lokasi baru tampil
    // (dan form edit tidak menimpa lokasi baru).
    private static function refreshEditPage(Component $livewire): void
    {
        if ($livewire instanceof EditRecord || $livewire instanceof ViewRecord) {
            $livewire->getRecord()->refresh();
        }

        if ($livewire instanceof EditRecord) {
            $livewire->refreshFormData(['room_id']);
        }
    }
}
