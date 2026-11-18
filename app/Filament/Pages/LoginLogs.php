<?php

namespace App\Filament\Pages;

use App\Enums\LoginResult;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\LoginLog;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Hanya baca: siapa yang masuk, gagal, terkunci, atau keluar, dari IP dan lokasi mana.
class LoginLogs extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static ?int $navigationSort = 97;

    protected static ?string $slug = 'log-masuk';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Administrasi');
    }

    public static function getNavigationLabel(): string
    {
        return __('Log masuk');
    }

    public function getTitle(): string
    {
        return __('Log masuk');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(LoginLog::query()->with('user'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Waktu'))
                    ->dateTime('j M Y H:i:s')
                    ->sortable(),
                TextColumn::make('hasil')
                    ->label(__('Hasil'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('Akun'))
                    ->placeholder(__('Tidak ada akun')),
                TextColumn::make('login')
                    ->label(__('Yang diketik'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->label(__('Alamat IP'))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('lokasi')
                    ->label(__('Lokasi'))
                    ->state(fn (LoginLog $record): ?string => collect([$record->kota, $record->countryName()])->filter()->implode(', ') ?: null)
                    ->placeholder(__('Tidak diketahui')),
                TextColumn::make('perangkat')
                    ->label(__('Perangkat'))
                    ->state(fn (LoginLog $record): ?string => $record->device())
                    ->tooltip(fn (LoginLog $record): ?string => $record->user_agent)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('hasil')
                    ->label(__('Hasil'))
                    ->options(LoginResult::class),
                SelectFilter::make('user_id')
                    ->label(__('Akun'))
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                Filter::make('ip')
                    ->schema([
                        TextInput::make('ip_address')
                            ->label(__('Alamat IP')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['ip_address'] ?? null, fn (Builder $query, string $ip) => $query->where('ip_address', $ip))),
                DateRangeFilter::make(),
            ]);
    }
}
