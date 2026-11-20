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

    // Kategori sebanyak ini tetap terbaca; sisanya digabung jadi "Lainnya".
    protected const int CATEGORY_LIMIT = 10;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Asset::class) ?? false;
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
     * Kategori terbesar lebih dulu; sisanya dijumlah jadi "Lainnya".
     *
     * @param  Collection<int, array<string, int|float>>  $rows  per category_id
     * @return Collection<string, array<string, int|float>> per nama kategori
     */
    protected function largestCategories(Collection $rows): Collection
    {
        $names = Category::query()->whereKey($rows->keys())->pluck('nama', 'id');
        $sorted = $rows->sortByDesc(fn (array $row): int|float => array_sum($row));

        $largest = $sorted->take(static::CATEGORY_LIMIT)->mapWithKeys(fn (array $row, int $categoryId): array => [$names[$categoryId] ?? "#{$categoryId}" => $row]);
        $rest = $sorted->skip(static::CATEGORY_LIMIT);

        if ($rest->isNotEmpty()) {
            $largest->put(__('Lainnya'), collect($rest->first())->map(fn (int|float $value, string $key): int|float => $rest->sum($key))->all());
        }

        return $largest;
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
