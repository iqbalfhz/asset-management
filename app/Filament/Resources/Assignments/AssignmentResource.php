<?php

namespace App\Filament\Resources\Assignments;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Enums\ReceiptMethod;
use App\Filament\Actions\DomainAction;
use App\Filament\Forms\Components\SignaturePad;
use App\Filament\Resources\Assignments\Pages\ListAssignments;
use App\Models\Assignment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

// Daftar transaksi saja; serah-terima dan pengembalian dibuat dari halaman aset.
class AssignmentResource extends Resource
{
    protected static ?string $model = Assignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('Serah-terima');
    }

    public static function getPluralModelLabel(): string
    {
        return trans_choice('Serah-terima|Serah-terima', 2);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Transaksi');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['asset', 'createdBy']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->label(__('Tanggal'))
                    ->date('j M Y')
                    ->sortable(),
                TextColumn::make('asset.kode')
                    ->label(__('Aset'))
                    ->sortable()
                    ->description(fn (Assignment $record): string => $record->asset->nama)
                    ->searchable()
                    ->hidden(fn (Component $livewire): bool => $livewire instanceof RelationManager),
                TextColumn::make('jenis')
                    ->label(__('Jenis'))
                    ->sortable()
                    ->badge(),
                TextColumn::make('pemegang')
                    ->label(__('Pemegang'))
                    ->state(fn (Assignment $record): string => $record->targetLabel()),
                TextColumn::make('kondisi')
                    ->label(__('Kondisi'))
                    ->sortable(),
                TextColumn::make('no_bast')
                    ->label(__('Nomor BAST'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('expected_return_at')
                    ->label(__('Tanggal kembali'))
                    ->sortable()
                    ->date('j M Y')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->sortable()
                    ->badge(),
                TextColumn::make('accepted_at')
                    ->label(__('Diterima pada'))
                    ->sortable()
                    ->dateTime('j M Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('createdBy.name')
                    ->label(__('Dicatat oleh'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(AssignmentStatus::class),
                SelectFilter::make('jenis')
                    ->label(__('Jenis'))
                    ->options(AssignmentType::class),
            ])
            ->recordActions([
                static::confirmReceiptAction(),
                Action::make('bast')
                    ->label(__('BAST'))
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn (Assignment $record): string => route('filament.admin.bast.download', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    private static function confirmReceiptAction(): Action
    {
        return Action::make('confirmReceipt')
            ->label(__('Konfirmasi penerimaan'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->visible(fn (Assignment $record): bool => $record->status === AssignmentStatus::Menunggu)
            ->authorize(fn (Assignment $record): bool => auth()->user()->can('update', $record->asset))
            ->modalHeading(fn (Assignment $record): string => __('Konfirmasi penerimaan BAST :nomor', ['nomor' => $record->no_bast]))
            ->modalDescription(__('Berlaku untuk semua aset dalam BAST ini yang masih dipegang karyawan. Foto ikut dicetak sebagai lampiran BAST.'))
            ->schema([
                ToggleButtons::make('bukti')
                    ->label(__('Bukti penerimaan'))
                    ->options([
                        ReceiptMethod::Kertas->value => __('Foto BAST yang sudah ditandatangani'),
                        ReceiptMethod::Layar->value => __('Tanda tangan di layar ini'),
                    ])
                    ->default(ReceiptMethod::Kertas->value)
                    ->inline()
                    ->required()
                    ->live()
                    ->dehydrated(false),
                FileUpload::make('foto')
                    ->label(__('Foto BAST yang sudah ditandatangani'))
                    ->helperText(__('Foto seluruh halaman BAST sampai tanda tangannya terbaca.'))
                    ->image()
                    // Tanpa SVG: SVG bisa membawa skrip saat dibuka di browser.
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    // Diperkecil di browser supaya BAST tetap ringan; data lokasi foto ikut terbuang.
                    ->automaticallyResizeImagesMode('contain')
                    ->automaticallyResizeImagesToWidth('1600')
                    ->automaticallyResizeImagesToHeight('1600')
                    ->automaticallyUpscaleImagesWhenResizing(false)
                    ->maxSize(5120)
                    ->storeFiles(false)
                    ->required()
                    ->visible(fn (Get $get): bool => $get('bukti') === ReceiptMethod::Kertas->value),
                SignaturePad::make('tanda_tangan')
                    ->label(__('Tanda tangan karyawan'))
                    ->required()
                    ->visible(fn (Get $get): bool => $get('bukti') === ReceiptMethod::Layar->value),
            ])
            ->modalSubmitActionLabel(__('Simpan'))
            ->action(fn (Assignment $record, array $data, Action $action) => DomainAction::run(
                $action,
                fn () => $record->confirmReceipt($data['tanda_tangan'] ?? null, $data['foto'] ?? null),
                __('Penerimaan tercatat'),
            ));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssignments::route('/'),
        ];
    }
}
