<?php

namespace App\Filament\Karyawan\Pages;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Filament\Karyawan\Actions\ReportDamageAction;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Scopes\ManagedCategoryScope;
use App\Models\Status;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;

class MyAssets extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'aset-saya';

    public static function getNavigationLabel(): string
    {
        return __('Aset saya');
    }

    public function getTitle(): string
    {
        return __('Aset saya');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::pendingAssignments()->distinct()->count('no_bast');

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return __('Serah-terima yang menunggu Anda terima');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $employee = auth()->user()->employee;

        return $table
            ->query(fn (): Builder => Asset::query()
                ->withoutGlobalScope(ManagedCategoryScope::class)
                ->with(['category', 'status', 'assetModel.manufacturer'])
                ->where('assigned_type', (new Employee)->getMorphClass())
                ->where('assigned_id', $employee?->getKey() ?? 0)
                ->whereHas('status', fn (Builder $query) => $query->whereIn('nama', [Status::DIPAKAI, Status::DALAM_PERBAIKAN])))
            ->emptyStateHeading(__('Belum ada aset yang Anda pegang'))
            ->emptyStateDescription($employee === null ? __('Akun Anda belum terhubung ke data karyawan. Hubungi admin.') : null)
            ->columns([
                TextColumn::make('kode'),
                TextColumn::make('nama')
                    ->description(fn (Asset $record): ?string => $record->assetModel ? "{$record->assetModel->manufacturer->nama} {$record->assetModel->nama}" : null),
                TextColumn::make('category.nama')
                    ->label(__('Kategori')),
                TextColumn::make('no_seri')
                    ->label(__('Nomor seri'))
                    ->placeholder('—'),
                TextColumn::make('status.nama')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (Asset $record): ?array => $record->status->warna ? Color::hex($record->status->warna) : null),
                TextColumn::make('penerimaan')
                    ->label(__('Penerimaan'))
                    ->badge()
                    ->state(fn (Asset $record): AssignmentStatus => static::pendingFor($record) ? AssignmentStatus::Menunggu : AssignmentStatus::Diterima),
            ])
            ->recordActions([
                $this->acceptAction(),
                ReportDamageAction::forAsset(),
            ]);
    }

    /**
     * Terima satu BAST sekaligus, setelah membaca syarat penggunaan kategorinya.
     */
    private function acceptAction(): Action
    {
        return Action::make('accept')
            ->label(__('Terima'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->button()
            ->visible(fn (Asset $record): bool => static::pendingFor($record) !== null)
            ->modalHeading(fn (Asset $record): string => __('Terima BAST :nomor', ['nomor' => static::pendingFor($record)->no_bast]))
            ->modalDescription(fn (Asset $record): string => __('Anda menyatakan sudah menerima aset berikut: :list.', [
                'list' => static::bastRows($record)->map(fn (Assignment $row): string => "{$row->asset->kode} ({$row->asset->nama})")->implode(', '),
            ]))
            ->schema(fn (Asset $record): array => [
                ...static::bastRows($record)
                    ->map(fn (Assignment $row) => $row->asset->category)
                    ->unique('id')
                    ->filter(fn ($category): bool => filled($category->eula))
                    ->map(fn ($category): Section => Section::make(__('Syarat penggunaan: :kategori', ['kategori' => $category->nama]))
                        ->schema([Text::make(new HtmlString(nl2br(e($category->eula))))->key("eula-{$category->id}")]))
                    ->values()
                    ->all(),
                Checkbox::make('setuju')
                    ->label(__('Saya sudah menerima aset di atas dan menyetujui syarat penggunaannya.'))
                    ->accepted(),
            ])
            ->modalSubmitActionLabel(__('Terima'))
            ->action(function (Asset $record): void {
                $count = Assignment::accept(auth()->user()->employee, static::pendingFor($record)->no_bast);

                Notification::make()
                    ->success()
                    ->title(__('Serah-terima diterima'))
                    ->body(__(':count aset tercatat sudah Anda terima.', ['count' => $count]))
                    ->send();
            });
    }

    /**
     * Serah-terima yang menunggu diterima karyawan yang login, untuk aset yang masih dipegangnya.
     *
     * @return Builder<Assignment>
     */
    private static function pendingAssignments(): Builder
    {
        $employee = auth()->user()?->employee;

        return Assignment::withoutGlobalScope('asset')
            ->where('jenis', AssignmentType::Keluar)
            ->where('status', AssignmentStatus::Menunggu)
            ->where('target_type', (new Employee)->getMorphClass())
            ->where('target_id', $employee?->getKey() ?? 0)
            ->whereHas('asset', fn (Builder $query) => $query->withoutGlobalScopes()
                ->where('assigned_type', (new Employee)->getMorphClass())
                ->where('assigned_id', $employee?->getKey() ?? 0));
    }

    private static function pendingFor(Asset $asset): ?Assignment
    {
        return static::pendingAssignments()->where('asset_id', $asset->getKey())->latest('id')->first();
    }

    /**
     * @return Collection<int, Assignment>
     */
    private static function bastRows(Asset $asset): Collection
    {
        return static::pendingAssignments()
            ->with(['asset' => fn (BelongsTo $query) => $query->withoutGlobalScopes()->with('category')])
            ->where('no_bast', static::pendingFor($asset)?->no_bast)
            ->get();
    }
}
