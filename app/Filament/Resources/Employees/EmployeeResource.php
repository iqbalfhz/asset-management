<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Filament\Resources\Employees\Pages\ViewEmployee;
use App\Filament\Resources\Employees\RelationManagers\AssetsRelationManager;
use App\Filament\Resources\Employees\RelationManagers\ConsumableTransactionsRelationManager;
use App\Filament\Resources\Employees\RelationManagers\LicenseSeatsRelationManager;
use App\Filament\Schemas\Components\CompanySelect;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\LicenseSeat;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use LogicException;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function getModelLabel(): string
    {
        return __('Karyawan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Karyawan');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Organisasi');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                CompanySelect::make(),
                TextInput::make('nik')
                    ->label(__('NIK'))
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('company_id', $get('company_id'))),
                TextInput::make('nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('jabatan')
                    ->maxLength(255),
                Select::make('department_id')
                    ->label(__('Departemen'))
                    ->relationship('department', 'nama', modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->where('company_id', $get('company_id')))
                    ->searchable()
                    ->preload(),
                Select::make('user_id')
                    ->label(__('Akun login'))
                    ->helperText(__('Boleh kosong; tidak semua karyawan punya akun.'))
                    ->relationship('user', 'name', modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->where('company_id', $get('company_id')))
                    ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->name} ({$record->username})")
                    ->searchable(['name', 'username'])
                    ->unique(ignoreRecord: true),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Karyawan'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('nik')
                            ->label(__('NIK')),
                        TextEntry::make('nama'),
                        TextEntry::make('jabatan')
                            ->placeholder('—'),
                        TextEntry::make('department.nama')
                            ->label(__('Departemen'))
                            ->placeholder('—'),
                        TextEntry::make('user.username')
                            ->label(__('Akun login'))
                            ->placeholder(__('Tidak punya akun')),
                        TextEntry::make('company.nama')
                            ->label(__('Perusahaan')),
                        IconEntry::make('is_active')
                            ->label(__('Aktif'))
                            ->boolean(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->columns([
                TextColumn::make('nik')
                    ->label(__('NIK'))
                    ->searchable(),
                TextColumn::make('nama')
                    ->searchable(),
                TextColumn::make('jabatan')
                    ->searchable(),
                TextColumn::make('department.nama')
                    ->label(__('Departemen')),
                TextColumn::make('user.username')
                    ->label(__('Akun login')),
                TextColumn::make('company.kode')
                    ->label(__('Perusahaan'))
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label(__('Aktif'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('Aktif')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                static::deactivateAction(),
                Action::make('reactivate')
                    ->label(__('Aktifkan'))
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (Employee $record): bool => ! $record->is_active)
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->modalDescription(__('Akun login karyawan tidak ikut aktif; aktifkan lewat halaman User kalau perlu.'))
                    ->action(fn (Employee $record) => $record->update(['is_active' => true])),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Offboarding: ditolak selama karyawan masih memegang aset atau seat lisensi; hanya Super Admin yang bisa memaksa dengan alasan.
     */
    private static function deactivateAction(): Action
    {
        $isSuperAdmin = fn (): bool => auth()->user()?->hasRole('super_admin') ?? false;
        $holdsSomething = fn (Employee $record): bool => $record->heldAssets()->exists() || $record->activeLicenseSeats()->exists();

        return Action::make('deactivate')
            ->label(__('Nonaktifkan'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->visible(fn (Employee $record): bool => $record->is_active)
            ->authorize('update')
            ->modalHeading(fn (Employee $record): string => __('Nonaktifkan :nama', ['nama' => $record->nama]))
            ->modalDescription(function (Employee $record) use ($isSuperAdmin): string {
                $held = $record->heldAssets()->get();
                $seats = $record->activeLicenseSeats()->get();

                if ($held->isEmpty() && $seats->isEmpty()) {
                    return __('Tidak ada aset atau seat lisensi yang masih dipegang. Akun login karyawan ini ikut dinonaktifkan.');
                }

                $list = $held->toBase()->map(fn (Asset $asset): string => "{$asset->kode} ({$asset->nama})")
                    ->merge($seats->toBase()->map(fn (LicenseSeat $seat): string => __('seat :software', ['software' => $seat->license->nama_software])))
                    ->implode(', ');
                $holdings = Employee::describeHoldings($held->count(), $seats->count());

                return $isSuperAdmin()
                    ? __('Masih memegang :barang: :list. Kalau dipaksa, aset itu ditandai Hilang, seat lisensinya dilepas, dan alasannya dicatat.', ['barang' => $holdings, 'list' => $list])
                    : __('Masih memegang :barang: :list. Proses pengembaliannya dulu di halaman Aset dan Lisensi.', ['barang' => $holdings, 'list' => $list]);
            })
            ->schema(fn (Employee $record): array => $isSuperAdmin() && $holdsSomething($record)
                ? [
                    Textarea::make('alasan')
                        ->label(__('Alasan menonaktifkan paksa'))
                        ->required()
                        ->maxLength(1000),
                ]
                : [])
            ->modalSubmitAction(fn (Action $action, Employee $record): Action => $action->disabled(! $isSuperAdmin() && $holdsSomething($record)))
            ->action(function (Employee $record, array $data, Action $action) use ($isSuperAdmin): void {
                try {
                    $record->deactivate($isSuperAdmin() ? ($data['alasan'] ?? null) : null);
                } catch (LogicException $exception) {
                    Notification::make()->danger()->title(__('Karyawan belum bisa dinonaktifkan'))->body($exception->getMessage())->send();
                    $action->halt();
                }

                Notification::make()->success()->title(__('Karyawan dinonaktifkan'))->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            AssetsRelationManager::class,
            LicenseSeatsRelationManager::class,
            ConsumableTransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployees::route('/'),
            'view' => ViewEmployee::route('/{record}'),
        ];
    }
}
