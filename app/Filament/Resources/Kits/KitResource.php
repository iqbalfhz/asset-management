<?php

namespace App\Filament\Resources\Kits;

use App\Filament\Actions\DomainAction;
use App\Filament\Resources\Assets\Actions\AssetTransactionActions;
use App\Filament\Resources\Kits\Pages\CreateKit;
use App\Filament\Resources\Kits\Pages\EditKit;
use App\Filament\Resources\Kits\Pages\ListKits;
use App\Filament\Schemas\Components\CompanySelect;
use App\Filament\Schemas\Components\EmployeeSelect;
use App\Filament\Tables\Columns\CompanyColumn;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Assignment;
use App\Models\Consumable;
use App\Models\Employee;
use App\Models\Kit;
use App\Models\KitItem;
use App\Models\License;
use App\Models\Status;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class KitResource extends Resource
{
    protected static ?string $model = Kit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function getModelLabel(): string
    {
        return __('Paket');
    }

    public static function getPluralModelLabel(): string
    {
        return trans_choice('Kits|Kits', 2);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                CompanySelect::make()
                    ->afterStateUpdated(fn (Set $set) => $set('items', [])),
                TextInput::make('nama')
                    ->label(__('Nama'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('company_id', $get('company_id'))),
                Textarea::make('keterangan')
                    ->label(__('Keterangan'))
                    ->maxLength(2000)
                    ->columnSpanFull(),
                Repeater::make('items')
                    ->label(__('Isi paket'))
                    ->relationship()
                    ->columns(3)
                    ->columnSpanFull()
                    ->minItems(1)
                    ->schema([
                        Select::make('item_type')
                            ->label(__('Jenis'))
                            ->options(KitItem::types())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('item_id', null)),
                        Select::make('item_id')
                            ->label(__('Barang'))
                            ->options(fn (Get $get): array => static::itemOptions($get('item_type'), $get('../../company_id')))
                            ->searchable()
                            ->required(),
                        TextInput::make('jumlah')
                            ->label(__('Jumlah'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(fn (Get $get): ?int => $get('item_type') === License::class ? 1 : null)
                            ->helperText(fn (Get $get): ?string => $get('item_type') === License::class ? __('Satu seat per karyawan.') : null)
                            ->default(1)
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['company', 'items.item']))
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('nama')
                    ->label(__('Nama'))
                    ->description(fn (Kit $record): ?string => $record->keterangan)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('isi')
                    ->label(__('Isi paket'))
                    ->state(fn (Kit $record): array => $record->items->map(fn (KitItem $line): string => $line->label())->all())
                    ->listWithLineBreaks(),
                CompanyColumn::make(),
            ])
            ->recordActions([
                static::handOverAction(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    private static function handOverAction(): Action
    {
        return Action::make('handOver')
            ->label(__('Serahkan paket'))
            ->icon(Heroicon::OutlinedArrowUpRight)
            ->authorize('update')
            ->modalHeading(fn (Kit $record): string => __('Serahkan :nama', ['nama' => $record->nama]))
            ->modalDescription(__('Setiap barang tercatat sendiri. Kalau satu barang gagal, tidak ada yang diserahkan.'))
            ->schema(fn (Kit $record): array => [
                EmployeeSelect::make(__('Karyawan'), $record->company_id),
                ...$record->items
                    ->filter(fn (KitItem $line): bool => $line->item instanceof AssetModel)
                    ->map(fn (KitItem $line): Select => static::assetChoice($line, $record->company_id))
                    ->all(),
                ...($record->hasAssets() ? AssetTransactionActions::handOverDetailFields() : []),
            ])
            ->action(function (Kit $record, array $data, Action $action): void {
                // Karyawan dicari ulang dengan hak lihat user, bukan dipercaya dari isian form.
                $noBast = DomainAction::attempt(
                    fn (): ?string => $record->handOver(Employee::query()->findOrFail($data['employee_id']), $data['assets'] ?? [], $data),
                    __('Paket belum diserahkan'),
                    fn () => $action->halt(),
                );

                if ($noBast !== null) {
                    AssetTransactionActions::notifySaved(__('Paket diserahkan'), $noBast);

                    return;
                }

                Notification::make()->success()->title(__('Paket diserahkan'))->send();
            });
    }

    // Aset Tersedia dari model itu di perusahaan paket; terisi otomatis dengan kode terkecil.
    private static function assetChoice(KitItem $line, int $companyId): Select
    {
        $options = Asset::query()
            ->where('asset_model_id', $line->item_id)
            ->where('company_id', $companyId)
            ->whereRelation('status', 'nama', Status::TERSEDIA)
            ->orderBy('kode')
            ->get()
            ->mapWithKeys(fn (Asset $asset): array => [$asset->getKey() => Assignment::labelFor($asset)])
            ->all();

        return Select::make("assets.{$line->getKey()}")
            ->label(__('Aset :model (:jumlah unit)', ['model' => $line->item->fullName(), 'jumlah' => $line->jumlah]))
            ->multiple()
            ->options($options)
            ->default(array_slice(array_keys($options), 0, $line->jumlah))
            ->minItems($line->jumlah)
            ->maxItems($line->jumlah)
            ->required();
    }

    /**
     * Barang yang bisa masuk paket; selain model aset, hanya dari perusahaan paket.
     *
     * @return array<int, string>
     */
    private static function itemOptions(?string $type, mixed $companyId): array
    {
        return match ($type) {
            AssetModel::class => AssetModel::query()->with('manufacturer')->orderBy('nama')->get()
                ->mapWithKeys(fn (AssetModel $model): array => [$model->getKey() => $model->fullName()])
                ->all(),
            Accessory::class => Accessory::query()->where('company_id', $companyId)->orderBy('nama')->pluck('nama', 'id')->all(),
            License::class => License::query()->where('company_id', $companyId)->orderBy('nama_software')->pluck('nama_software', 'id')->all(),
            Consumable::class => Consumable::query()->where('company_id', $companyId)->orderBy('nama')->pluck('nama', 'id')->all(),
            default => [],
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKits::route('/'),
            'create' => CreateKit::route('/create'),
            'edit' => EditKit::route('/{record}/edit'),
        ];
    }
}
