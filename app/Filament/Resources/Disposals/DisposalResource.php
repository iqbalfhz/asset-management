<?php

namespace App\Filament\Resources\Disposals;

use App\Enums\DisposalMethod;
use App\Enums\DisposalStatus;
use App\Filament\Resources\Disposals\Pages\CreateDisposal;
use App\Filament\Resources\Disposals\Pages\ListDisposals;
use App\Models\Asset;
use App\Models\Disposal;
use App\Models\Status;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

class DisposalResource extends Resource
{
    protected static ?string $model = Disposal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxXMark;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('Penghapusan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Penghapusan');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('asset_id')
                    ->label(__('Aset'))
                    ->helperText(__('Hanya aset berstatus Rusak atau Hilang.'))
                    ->relationship('asset', 'kode', modifyQueryUsing: fn (Builder $query): Builder => $query
                        ->whereHas('status', fn (Builder $query) => $query->whereIn('nama', [Status::RUSAK, Status::HILANG])))
                    ->getOptionLabelFromRecordUsing(fn (Asset $record): string => "{$record->kode} — {$record->nama}")
                    ->searchable(['kode', 'nama'])
                    ->required(),
                Select::make('metode')
                    ->label(__('Metode'))
                    ->options(DisposalMethod::class)
                    ->required()
                    ->live(),
                TextInput::make('nilai_jual')
                    ->label(__('Nilai jual'))
                    ->integer()
                    ->minValue(0)
                    ->prefix('Rp')
                    ->visible(fn (Get $get): bool => in_array($get('metode'), [DisposalMethod::Dijual, DisposalMethod::Dijual->value], true)),
                Textarea::make('alasan')
                    ->label(__('Alasan'))
                    ->required()
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $isSuperAdmin = fn (): bool => auth()->user()?->hasRole('super_admin') ?? false;

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['asset', 'submitter', 'decider']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Diajukan'))
                    ->date('j M Y'),
                TextColumn::make('asset.kode')
                    ->label(__('Aset'))
                    ->description(fn (Disposal $record): string => $record->asset->nama)
                    ->searchable(),
                TextColumn::make('metode')
                    ->badge(),
                TextColumn::make('nilai_jual')
                    ->label(__('Nilai jual'))
                    ->money('IDR', locale: 'id')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('submitter.name')
                    ->label(__('Pengaju')),
                TextColumn::make('no_berita_acara')
                    ->label(__('Nomor berita acara'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('catatan')
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(DisposalStatus::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('Setujui'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (Disposal $record): bool => $record->status === DisposalStatus::Diajukan && $isSuperAdmin())
                    ->modalDescription(__('Aset menjadi Dihapuskan dan berita acara terbit. Langkah ini tidak bisa dibatalkan.'))
                    ->schema([
                        Textarea::make('catatan')
                            ->label(__('Catatan (opsional)'))
                            ->maxLength(2000),
                    ])
                    ->modalSubmitActionLabel(__('Setujui'))
                    ->action(fn (Disposal $record, array $data, Action $action) => static::decide($action, fn () => $record->approve($data['catatan'] ?? null), __('Penghapusan disetujui'))),
                Action::make('reject')
                    ->label(__('Tolak'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->visible(fn (Disposal $record): bool => $record->status === DisposalStatus::Diajukan && $isSuperAdmin())
                    ->schema([
                        Textarea::make('catatan')
                            ->label(__('Alasan ditolak'))
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->modalSubmitActionLabel(__('Tolak'))
                    ->action(fn (Disposal $record, array $data, Action $action) => static::decide($action, fn () => $record->reject($data['catatan']), __('Penghapusan ditolak'))),
                Action::make('report')
                    ->label(__('Berita acara'))
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->visible(fn (Disposal $record): bool => $record->status === DisposalStatus::Disetujui)
                    ->url(fn (Disposal $record): string => route('filament.admin.disposals.report', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    private static function decide(Action $action, callable $decision, string $successTitle): void
    {
        try {
            $decision();
        } catch (LogicException $exception) {
            Notification::make()->danger()->title(__('Aksi dibatalkan'))->body($exception->getMessage())->send();
            $action->halt();
        }

        Notification::make()->success()->title($successTitle)->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDisposals::route('/'),
            'create' => CreateDisposal::route('/create'),
        ];
    }
}
