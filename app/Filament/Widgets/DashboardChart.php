<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\FiltersByDashboard;
use App\Models\Asset;
use App\Models\Category;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

// Dasar grafik dashboard: ikut filter Dashboard, tanpa refresh tiap 5 detik, dan berisi tabel untuk pembaca layar.
abstract class DashboardChart extends ChartWidget
{
    use FiltersByDashboard;

    // Lolos validator buta warna dan kontras di mode terang dan gelap; satu makna per warna di semua grafik.
    public const string GREEN = '#2aa577';

    public const string BLUE = '#3d6cb8';

    public const string BRICK = '#b5432c';

    // Batang sebanyak ini tetap terbaca; sisanya digabung jadi "Lainnya".
    protected const int BAR_LIMIT = 10;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Asset::class) ?? false;
    }

    // Tanpa data (semua nol), tampilkan keterangan kosong, bukan sumbu kosong yang terlihat seperti rusak.
    public function isEmpty(): bool
    {
        return collect($this->getCachedData()['datasets'] ?? [])
            ->flatMap(fn (array $dataset): array => $dataset['data'])
            ->every(fn (int|float $value): bool => (float) $value === 0.0);
    }

    public function getEmptyStateHeading(): string
    {
        return __('Belum ada data');
    }

    public function getEmptyStateDescription(): string
    {
        return __('Grafik muncul setelah ada data aset yang cocok dengan filter dan periode ini.');
    }

    public function getChartAssistiveContent(): View
    {
        return view('filament.widgets.chart-table', ['data' => $this->getCachedData(), 'caption' => $this->getHeading()]);
    }

    /**
     * @param  array<int, int|float>  $data
     * @param  string|array<int, string>  $color
     * @return array<string, mixed>
     */
    protected function bars(string $label, array $data, string|array $color): array
    {
        // Garis tepi transparan memberi celah 2px antar-batang dan antar-segmen.
        return ['label' => $label, 'data' => $data, 'backgroundColor' => $color, 'borderColor' => 'rgba(0,0,0,0)', 'borderWidth' => 1, 'borderRadius' => 4];
    }

    /**
     * @param  array<int, int|float>  $data
     * @return array<string, mixed>
     */
    protected function line(string $label, array $data, string $color): array
    {
        return ['label' => $label, 'data' => $data, 'borderColor' => $color, 'backgroundColor' => $color, 'borderWidth' => 2, 'pointRadius' => 4, 'tension' => 0, 'fill' => false];
    }

    /**
     * Yang terbesar lebih dulu; sisanya dijumlah jadi "Lainnya".
     *
     * @param  Collection<int, array<string, int|float>>  $rows  per id
     * @param  Collection<int, string>  $names  per id
     * @return Collection<int, array<string, int|float|string>> tiap baris berisi 'label' dan nilainya
     */
    protected function largestOf(Collection $rows, Collection $names): Collection
    {
        $sorted = $rows->sortByDesc(fn (array $row): int|float => array_sum($row));

        $largest = $sorted->take(static::BAR_LIMIT)->map(fn (array $row, int $id): array => ['label' => $names[$id] ?? "#{$id}", ...$row])->values();
        $rest = $sorted->skip(static::BAR_LIMIT);

        if ($rest->isNotEmpty()) {
            $largest->push(['label' => __('Lainnya'), ...collect($rest->first())->map(fn (int|float $value, string $key): int|float => $rest->sum($key))->all()]);
        }

        return $largest;
    }

    /**
     * @param  Collection<int, array<string, int|float>>  $rows  per category_id
     * @return Collection<int, array<string, int|float|string>>
     */
    protected function largestCategories(Collection $rows): Collection
    {
        return $this->largestOf($rows, Category::query()->whereKey($rows->keys())->pluck('nama', 'id'));
    }

    /**
     * @param  array<string, mixed>  $scales
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function chartOptions(array $scales, array $extra = []): array
    {
        return [
            // Legenda hanya kalau ada lebih dari satu seri; seri tunggal sudah dinamai judulnya.
            'plugins' => ['legend' => ['display' => count($this->getCachedData()['datasets']) > 1, 'position' => 'bottom']],
            'scales' => $scales,
            ...$extra,
        ];
    }

    // Rupiah dalam juta, satu desimal, supaya sumbu tetap pendek.
    protected static function millions(int|float $rupiah): float
    {
        return round($rupiah / 1_000_000, 1);
    }
}
