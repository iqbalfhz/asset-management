<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Actions\DomainAction;
use App\Models\AssetReminder;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class RemindersRelationManager extends RelationManager
{
    protected static string $relationship = 'reminders';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans_choice('Pengingat|Pengingat', 2);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('jenis')
                    ->label(__('Jenis'))
                    ->datalist([__('Pajak STNK'), __('Pajak 5 tahunan (ganti plat)'), __('Servis berkala'), __('Akhir kontrak sewa'), __('Perpanjangan asuransi')])
                    ->required()
                    ->maxLength(255),
                DatePicker::make('tanggal')
                    ->label(__('Jatuh tempo'))
                    ->required(),
                TextInput::make('ulangi_bulan')
                    ->label(__('Ulangi setiap'))
                    ->helperText(__('Kosongkan kalau tidak berulang. Contoh: 12 untuk pajak tahunan, 6 untuk servis.'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(120)
                    ->suffix(__('bulan')),
                Textarea::make('catatan')
                    ->label(__('Catatan'))
                    ->rows(2)
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('Pengingat'))
            ->pluralModelLabel(trans_choice('Pengingat|Pengingat', 2))
            ->defaultSort('tanggal')
            ->emptyStateHeading(__('Belum ada pengingat'))
            ->columns([
                TextColumn::make('jenis')
                    ->label(__('Jenis'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tanggal')
                    ->label(__('Jatuh tempo'))
                    ->date('j M Y')
                    ->color(fn (AssetReminder $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('ulangi_bulan')
                    ->label(__('Ulangi'))
                    ->formatStateUsing(fn (int $state): string => __('Setiap :bulan bulan', ['bulan' => $state]))
                    ->placeholder(__('Sekali'))
                    ->sortable(),
                TextColumn::make('catatan')
                    ->label(__('Catatan'))
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('selesai_pada')
                    ->label(__('Selesai'))
                    ->date('j M Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Tambah pengingat')),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label(__('Tandai selesai'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (AssetReminder $record): bool => $record->selesai_pada === null && $this->canEdit($record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (AssetReminder $record): string => $record->nextDate()
                        ? __('Tanggal berikutnya menjadi :tanggal.', ['tanggal' => $record->nextDate()->translatedFormat('j M Y')])
                        : __('Pengingat ini ditutup.'))
                    ->action(fn (AssetReminder $record, Action $action) => DomainAction::run($action, fn () => $record->complete(), __('Pengingat diperbarui'))),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    // Menambah, mengubah, dan menghapus pengingat ikut izin ubah asetnya, bukan izin terpisah.
    public function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        if (in_array($action, ['create', 'update', 'delete'], true)) {
            return Gate::inspect('update', $this->getOwnerRecord());
        }

        return parent::getAuthorizationResponse($action, $record);
    }
}
