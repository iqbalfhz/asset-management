<?php

namespace App\Filament\Karyawan\Pages;

use App\Filament\Actions\DomainAction;
use App\Filament\Karyawan\EmployeeTablePage;
use App\Filament\Tables\AssetBookingTable;
use App\Models\Asset;
use App\Models\AssetBooking;
use App\Models\Assignment;
use App\Models\Status;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

// Pesan barang yang dipakai bergantian (proyektor, kamera); langsung terpesan kalau jadwalnya kosong.
class MyBookings extends EmployeeTablePage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'pinjam-barang';

    public static function getNavigationLabel(): string
    {
        return __('Pinjam barang');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->bookAction(),
        ];
    }

    public function table(Table $table): Table
    {
        $employee = auth()->user()->employee;

        return $this->emptyState($table, __('Belum ada pesanan'))
            ->query(fn (): Builder => AssetBooking::withoutGlobalScopes()
                ->with(['asset' => fn (BelongsTo $query) => $query->withoutGlobalScopes()])
                ->where('employee_id', $employee?->getKey() ?? 0))
            ->defaultSort('mulai', 'desc')
            ->columns([
                AssetBookingTable::item(),
                AssetBookingTable::period(),
                AssetBookingTable::purpose(),
                AssetBookingTable::status(),
                AssetBookingTable::cancelReason()
                    ->wrap(),
            ])
            ->recordActions([
                AssetBookingTable::cancelAction()
                    ->requiresConfirmation(),
            ]);
    }

    private function bookAction(): Action
    {
        return Action::make('book')
            ->label(__('Pesan barang'))
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (): bool => auth()->user()->employee !== null)
            ->modalDescription(__('Pesanan langsung tercatat kalau jamnya belum dipakai orang lain. Ambil dan kembalikan barangnya di tempat biasa.'))
            ->schema([
                Select::make('asset_id')
                    ->label(__('Barang'))
                    ->options(fn (): array => static::bookableAssets()->mapWithKeys(fn (Asset $asset): array => [$asset->getKey() => Assignment::labelFor($asset)])->all())
                    ->searchable()
                    ->required()
                    ->live(),
                Text::make(fn (Get $get): string => static::scheduleOf($get('asset_id')))
                    ->visible(fn (Get $get): bool => filled($get('asset_id'))),
                DateTimePicker::make('mulai')
                    ->label(__('Mulai'))
                    ->seconds(false)
                    ->minDate(now()->startOfMinute())
                    ->required(),
                DateTimePicker::make('selesai')
                    ->label(__('Selesai'))
                    ->seconds(false)
                    ->after('mulai')
                    ->required(),
                Textarea::make('keperluan')
                    ->label(__('Keperluan'))
                    ->placeholder(__('Contoh: presentasi ke klien di ruang rapat lantai 3.'))
                    ->rows(2)
                    ->required()
                    ->maxLength(1000),
            ])
            ->modalSubmitActionLabel(__('Pesan'))
            ->action(fn (array $data, Action $action) => DomainAction::run(
                $action,
                fn () => AssetBooking::book(
                    static::bookableAssets()->findOrFail($data['asset_id']),
                    auth()->user()->employee,
                    Carbon::parse($data['mulai']),
                    Carbon::parse($data['selesai']),
                    $data['keperluan'],
                ),
                __('Barang terpesan'),
                __('Pesanan belum tercatat'),
            ));
    }

    /**
     * Barang yang bisa dipesan karyawan yang login: milik perusahaannya, ditandai bisa dipesan, dan sedang Tersedia.
     *
     * @return Collection<int, Asset>
     */
    private static function bookableAssets(): Collection
    {
        return Asset::withoutGlobalScopes()
            ->where('company_id', auth()->user()->employee->company_id)
            ->where('bookable', true)
            ->whereRelation('status', 'nama', Status::TERSEDIA)
            ->orderBy('kode')
            ->get();
    }

    // Jadwal yang sudah terisi, supaya karyawan memilih jam yang kosong.
    private static function scheduleOf(mixed $assetId): string
    {
        $bookings = AssetBooking::withoutGlobalScopes()
            ->active()
            ->where('asset_id', $assetId)
            ->where('selesai', '>', now())
            ->orderBy('mulai')
            ->limit(10)
            ->get();

        return $bookings->isEmpty()
            ? __('Belum ada jadwal; semua jam masih kosong.')
            : __('Sudah dipesan: :jadwal', ['jadwal' => $bookings->map(fn (AssetBooking $booking): string => $booking->period())->implode('; ')]);
    }
}
