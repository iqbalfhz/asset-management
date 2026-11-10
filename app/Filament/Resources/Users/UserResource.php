<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Schemas\Components\CompanySelect;
use App\Models\Role;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    // Hanya Super Admin, apa pun izin yang dicentang di halaman Roles, supaya admin tidak bisa menaikkan role sendiri.
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function getModelLabel(): string
    {
        return __('User');
    }

    public static function getPluralModelLabel(): string
    {
        return __('User');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Administrasi');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Nama'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('username')
                    ->required()
                    ->alphaDash()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                CompanySelect::make(),
                // Satu user = satu role: dipilih tunggal lalu disinkronkan ke tabel role Spatie.
                Select::make('role')
                    ->label(__('Role'))
                    ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->map(fn (string $name): string => Str::headline($name))->all())
                    ->required()
                    ->afterStateHydrated(fn (Select $component, ?User $record) => $component->state($record?->roles->first()?->getKey()))
                    ->dehydrated(false)
                    ->saveRelationshipsUsing(fn (User $record, $state) => $record->syncRoles([Role::findById((int) $state)])),
                Toggle::make('is_active')
                    ->label(__('Aktif'))
                    ->default(true)
                    ->disabled(fn (?User $record): bool => (bool) $record?->is(auth()->user())),
                TextInput::make('password')
                    ->label(__('Password awal'))
                    ->password()
                    ->revealable()
                    ->rule(Password::default())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->visible(fn (string $operation): bool => $operation === 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nama'))
                    ->searchable(),
                TextColumn::make('username')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label(__('Role'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state)),
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
                EditAction::make(),
                Action::make('resetPassword')
                    ->label(__('Reset password'))
                    ->icon(Heroicon::OutlinedKey)
                    ->schema([
                        TextInput::make('password')
                            ->label(__('Password baru'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::default())
                            ->same('passwordConfirmation'),
                        TextInput::make('passwordConfirmation')
                            ->label(__('Ulangi password baru'))
                            ->password()
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update(['password' => $data['password']]);

                        Notification::make()->title(__('Password sudah diganti.'))->success()->send();
                    }),
                Action::make('resetTwoFactor')
                    ->label(__('Reset 2FA'))
                    ->icon(Heroicon::OutlinedShieldExclamation)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('User harus mengatur ulang aplikasi authenticator saat login berikutnya.'))
                    ->visible(fn (User $record): bool => filled($record->getAppAuthenticationSecret()))
                    ->action(function (User $record): void {
                        $record->forceFill([
                            'app_authentication_secret' => null,
                            'app_authentication_recovery_codes' => null,
                        ])->save();

                        Notification::make()->title(__('2FA sudah direset.'))->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
