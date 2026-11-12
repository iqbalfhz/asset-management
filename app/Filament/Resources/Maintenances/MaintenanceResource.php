<?php

namespace App\Filament\Resources\Maintenances;

use App\Enums\MaintenanceStatus;
use App\Filament\Resources\Maintenances\Pages\CreateMaintenance;
use App\Filament\Resources\Maintenances\Pages\ListMaintenances;
use App\Models\Asset;
use App\Models\Maintenance;
use App\Models\Status;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use LogicException;

class MaintenanceResource extends Resource
{
    protected static ?string $model = Maintenance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('Perbaikan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Perbaikan');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Maintenance::query()->where('status', MaintenanceStatus::Dilaporkan)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return __('Laporan kerusakan yang belum dicek');
    }

    // Form hanya untuk perbaikan langsung oleh admin; laporan karyawan diproses lewat aksi di tabel.
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('asset_id')
                    ->label(__('Aset'))
                    ->relationship('asset', 'kode', modifyQueryUsing: fn (Builder $query): Builder => $query
                        ->whereHas('status', fn (Builder $query) => $query->whereIn('nama', [Status::TERSEDIA, Status::DIPAKAI, Status::RUSAK])))
                    ->getOptionLabelFromRecordUsing(fn (Asset $record): string => "{$record->kode} — {$record->nama}")
                    ->searchable(['kode', 'nama'])
                    ->required(),
                Textarea::make('keluhan')
                    ->label(__('Pekerjaan atau keluhan'))
                    ->required()
                    ->maxLength(2000),
                ...static::processFields(),
            ]);
    }

    /**
     * @return array<int, Select|DatePicker|Textarea>
     */
    public static function processFields(): array
    {
        return [
            Select::make('supplier_id')
                ->label(__('Vendor'))
                ->helperText(__('Kosongkan kalau dikerjakan tim sendiri.'))
                ->relationship('supplier', 'nama')
                ->searchable()
                ->preload(),
            DatePicker::make('tgl_mulai')
                ->label(__('Tanggal mulai'))
                ->default(now())
                ->required(),
            Textarea::make('catatan_admin')
                ->label(__('Catatan'))
                ->maxLength(2000),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['asset', 'reporter', 'supplier']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Masuk'))
                    ->date('j M Y'),
                TextColumn::make('asset.kode')
                    ->label(__('Aset'))
                    ->description(fn (Maintenance $record): string => $record->asset->nama)
                    ->searchable()
                    ->hidden(fn (Component $livewire): bool => $livewire instanceof RelationManager),
                TextColumn::make('reporter.nama')
                    ->label(__('Pelapor'))
                    ->placeholder(__('Admin')),
                TextColumn::make('keluhan')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('supplier.nama')
                    ->label(__('Vendor'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('biaya')
                    ->money('IDR', locale: 'id')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('hasil')
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MaintenanceStatus::class),
            ])
            ->recordActions([
                static::transition('process', __('Proses'), Heroicon::OutlinedPlay, MaintenanceStatus::Dilaporkan)
                    ->modalDescription(__('Aset menjadi Dalam Perbaikan sampai perbaikan selesai.'))
                    ->schema(static::processFields())
                    ->action(fn (Maintenance $record, array $data, Action $action) => static::run($action, fn () => $record->process($data), __('Perbaikan diproses'))),
                static::transition('reject', __('Tolak'), Heroicon::OutlinedXMark, MaintenanceStatus::Dilaporkan)
                    ->color('gray')
                    ->schema([
                        Textarea::make('catatan_admin')
                            ->label(__('Alasan ditolak'))
                            ->helperText(__('Terlihat oleh karyawan yang melapor.'))
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->action(fn (Maintenance $record, array $data, Action $action) => static::run($action, fn () => $record->reject($data['catatan_admin']), __('Laporan ditolak'))),
                static::transition('finish', __('Selesai'), Heroicon::OutlinedCheck, MaintenanceStatus::Diproses)
                    ->modalDescription(__('Aset kembali ke status sebelum diperbaiki, dengan pemegang yang sama.'))
                    ->schema(static::closeFields())
                    ->action(fn (Maintenance $record, array $data, Action $action) => static::run($action, fn () => $record->finish($data), __('Perbaikan selesai'))),
                static::transition('markBroken', __('Tidak bisa diperbaiki'), Heroicon::OutlinedExclamationTriangle, MaintenanceStatus::Diproses)
                    ->color('danger')
                    ->modalDescription(__('Aset menjadi Rusak dan bisa diajukan penghapusan.'))
                    ->schema(static::closeFields())
                    ->action(fn (Maintenance $record, array $data, Action $action) => static::run($action, fn () => $record->markBroken($data), __('Aset ditandai Rusak'))),
                Action::make('photo')
                    ->label(__('Foto'))
                    ->icon(Heroicon::OutlinedPhoto)
                    ->visible(fn (Maintenance $record): bool => filled($record->foto))
                    ->url(fn (Maintenance $record): string => route('filament.admin.maintenances.photo', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    private static function transition(string $name, string $label, Heroicon $icon, MaintenanceStatus $from): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->visible(fn (Maintenance $record): bool => $record->status === $from)
            ->authorize('update')
            ->modalSubmitActionLabel($label);
    }

    /**
     * @return array<int, Textarea|TextInput|DatePicker>
     */
    private static function closeFields(): array
    {
        return [
            Textarea::make('hasil')
                ->label(__('Hasil pekerjaan'))
                ->required()
                ->maxLength(2000),
            TextInput::make('biaya')
                ->label(__('Biaya'))
                ->integer()
                ->minValue(0)
                ->prefix('Rp'),
            DatePicker::make('tgl_selesai')
                ->label(__('Tanggal selesai'))
                ->default(now())
                ->required(),
        ];
    }

    private static function run(Action $action, Closure $transition, string $successTitle): void
    {
        try {
            $transition();
        } catch (LogicException $exception) {
            Notification::make()->danger()->title(__('Aksi dibatalkan'))->body($exception->getMessage())->send();
            $action->halt();
        }

        Notification::make()->success()->title($successTitle)->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenances::route('/'),
            'create' => CreateMaintenance::route('/create'),
        ];
    }
}
