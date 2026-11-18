<?php

namespace App\Filament\Pages;

use App\Enums\CalendarEventType;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

// Tanggal kembali, perbaikan, audit, dan lisensi habis dalam satu bulan; tiap jenis hanya untuk yang boleh melihatnya.
class Calendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'kalender';

    protected string $view = 'filament.pages.calendar';

    // Bulan yang dibuka, contoh 2026-11; kosong atau tidak sah berarti bulan ini.
    #[Url]
    public string $bulan = '';

    public static function getNavigationLabel(): string
    {
        return __('Kalender');
    }

    public function getTitle(): string
    {
        return __('Kalender');
    }

    public function getSubheading(): string
    {
        return $this->month()->translatedFormat('F Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previous')
                ->label(__('Bulan lalu'))
                ->icon(Heroicon::ChevronLeft)
                ->color('gray')
                ->action(fn () => $this->bulan = $this->month()->subMonth()->format('Y-m')),
            Action::make('current')
                ->label(__('Bulan ini'))
                ->color('gray')
                ->action(fn () => $this->bulan = ''),
            Action::make('next')
                ->label(__('Bulan depan'))
                ->icon(Heroicon::ChevronRight)
                ->iconPosition(IconPosition::After)
                ->color('gray')
                ->action(fn () => $this->bulan = $this->month()->addMonth()->format('Y-m')),
        ];
    }

    public function month(): CarbonImmutable
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->bulan)
            ? CarbonImmutable::createFromFormat('!Y-m', $this->bulan)
            : CarbonImmutable::today()->startOfMonth();
    }

    /**
     * Minggu-minggu penuh (Senin sampai Minggu) yang memuat bulan ini, beserta jadwal per tanggal.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $month = $this->month();
        $start = $month->startOfWeek(CarbonInterface::MONDAY);
        $end = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY);

        return [
            'month' => $month,
            'days' => collect(CarbonPeriod::create($start, $end)->toArray())->map(fn (CarbonInterface $day): CarbonImmutable => $day->toImmutable()),
            'entries' => $this->entriesBetween($start, $end)->groupBy(fn (array $entry): string => $entry['date']->toDateString()),
            'types' => CalendarEventType::cases(),
        ];
    }

    /**
     * @return Collection<int, array{date: CarbonInterface, type: CalendarEventType, label: string, detail: string, url: string}>
     */
    private function entriesBetween(CarbonInterface $start, CarbonInterface $end): Collection
    {
        return collect(CalendarEventType::cases())
            ->filter(fn (CalendarEventType $type): bool => auth()->user()->can('viewAny', $type->model()))
            ->flatMap(fn (CalendarEventType $type): Collection => $type->entriesBetween($start, $end));
    }
}
