<?php

namespace App\Filament\Pages;

use App\Filament\Tables\Columns\ActivityColumns;
use App\Models\Asset;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

// Hanya baca: isi log tidak bisa diubah atau dihapus dari panel.
class ActivityLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 98;

    protected static ?string $slug = 'log-aktivitas';

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
        return __('Log aktivitas');
    }

    public function getTitle(): string
    {
        return __('Log aktivitas');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->with(['causer', 'subject' => fn ($query) => $query->withoutGlobalScopes()]))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Waktu'))
                    ->dateTime('j M Y H:i:s'),
                TextColumn::make('causer.name')
                    ->label(__('Oleh'))
                    ->placeholder(__('Sistem')),
                ActivityColumns::event(),
                TextColumn::make('subject_type')
                    ->label(__('Data'))
                    ->formatStateUsing(fn (Activity $record): string => static::subjectLabel($record)),
                ActivityColumns::changes(),
            ])
            ->filters([
                SelectFilter::make('causer_id')
                    ->label(__('User'))
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $data['value'])),
                Filter::make('aset')
                    ->schema([
                        Select::make('asset_id')
                            ->label(__('Aset'))
                            ->getSearchResultsUsing(fn (string $search): array => Asset::withoutGlobalScopes()
                                ->where('kode', 'like', "%{$search}%")
                                ->orWhere('nama', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Asset $asset): array => [$asset->id => "{$asset->kode} — {$asset->nama}"])
                                ->all())
                            ->getOptionLabelUsing(fn (mixed $value): ?string => Asset::withoutGlobalScopes()->find($value)?->kode)
                            ->searchable(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => blank($data['asset_id'] ?? null)
                        ? $query
                        : $query->where('subject_type', (new Asset)->getMorphClass())->where('subject_id', $data['asset_id'])),
                Filter::make('tanggal')
                    ->schema([
                        DatePicker::make('dari')
                            ->label(__('Dari tanggal')),
                        DatePicker::make('sampai')
                            ->label(__('Sampai tanggal')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['sampai'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ]);
    }

    private static function subjectLabel(Activity $record): string
    {
        $type = class_basename($record->subject_type ?? '');
        $subject = $record->subject;

        $name = match (true) {
            $subject instanceof Model && filled($subject->getAttribute('kode')) => $subject->getAttribute('kode'),
            $subject instanceof Model && filled($subject->getAttribute('nama')) => $subject->getAttribute('nama'),
            $subject instanceof Model && filled($subject->getAttribute('name')) => $subject->getAttribute('name'),
            default => "#{$record->subject_id}",
        };

        return "{$type}: {$name}";
    }
}
