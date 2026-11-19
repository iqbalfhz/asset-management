<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\ManagedCategoryScope;
use App\Models\Setting;
use App\Models\Status;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DownloadAssetReportController extends Controller
{
    /**
     * Asetnya mengikuti hak lihat pengunduh; aset yang dihapuskan dan BYOD tidak ikut.
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Asset::class);

        $input = $request->validate([
            'perusahaan' => ['required', 'integer'],
            'departemen' => ['nullable', 'integer'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
        ]);

        $restrictedCompanyId = CompanyScope::restrictedCompanyId();
        abort_if($restrictedCompanyId !== null && $restrictedCompanyId !== (int) $input['perusahaan'], 404);

        $company = Company::query()->findOrFail($input['perusahaan']);
        $department = isset($input['departemen']) ? Department::query()->findOrFail($input['departemen']) : null;
        $date = CarbonImmutable::parse($input['tanggal']);
        $departmentOf = $this->departmentResolver($company);
        $departmentNames = Department::query()->pluck('nama', 'id');

        $rows = Asset::query()
            ->with(['category', 'status', 'depreciation'])
            ->where('company_id', $company->getKey())
            ->where('is_byod', false)
            ->whereRelation('status', 'nama', '!=', Status::DIHAPUSKAN)
            ->get()
            ->map(function (Asset $asset) use ($departmentOf, $departmentNames, $date): array {
                $departmentId = $departmentOf($asset);

                return [
                    'kategori' => $asset->category->nama,
                    'status' => $asset->status->label(),
                    'department_id' => $departmentId,
                    'departemen' => $departmentId === null ? '' : $departmentNames->get($departmentId, ''),
                    'harga' => $asset->harga_beli,
                    'nilai' => $asset->bookValue($date),
                ];
            })
            ->when($department, fn (Collection $rows) => $rows->where('department_id', $department->getKey()));

        return Pdf::loadView('pdf.laporan-aset', [
            'company' => $company,
            'department' => $department,
            'date' => $date,
            'total' => $this->summarize($rows),
            'sections' => array_values(array_filter([
                ['title' => __('Per kategori'), 'column' => __('Kategori'), 'groups' => $this->groupedBy($rows, 'kategori')],
                ['title' => __('Per status'), 'column' => __('Status'), 'groups' => $this->groupedBy($rows, 'status')],
                $department ? null : ['title' => __('Per departemen'), 'column' => __('Departemen'), 'groups' => $this->groupedBy($rows, 'departemen')],
            ])),
            'withoutValue' => $rows->whereNull('nilai')->count(),
            'restricted' => ManagedCategoryScope::restrictedRoleIds() !== null,
            'kop' => Setting::get('kop_bast', null, $company->getKey()),
            'logo' => Setting::logoDataUri($company->getKey()),
        ])->setPaper('a4')->download(Str::of("Laporan aset {$company->kode} {$date->format('Y-m-d')}")->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-').'.pdf');
    }

    /**
     * Departemen pemegang tiap aset: departemen itu sendiri, departemen karyawan pemegangnya, atau milik aset induknya.
     *
     * @return Closure(Asset): ?int
     */
    private function departmentResolver(Company $company): Closure
    {
        // Tanpa scope: aset induk dan karyawan pemegang tetap terbaca walau di luar kategori yang dikelola pengunduh.
        $parents = Asset::withoutGlobalScopes()->where('company_id', $company->getKey())->get(['id', 'assigned_type', 'assigned_id'])->keyBy('id');
        $employeeDepartments = Employee::withoutGlobalScopes()->where('company_id', $company->getKey())->pluck('department_id', 'id');
        [$assetType, $departmentType, $employeeType] = [(new Asset)->getMorphClass(), (new Department)->getMorphClass(), (new Employee)->getMorphClass()];

        return function (Asset $asset) use ($parents, $employeeDepartments, $assetType, $departmentType, $employeeType): ?int {
            [$type, $id] = [$asset->assigned_type, $asset->assigned_id];

            // Batas langkah menjaga dari rantai aset induk yang berputar.
            for ($step = 0; $type === $assetType && $step < 10; $step++) {
                [$type, $id] = [$parents->get($id)?->assigned_type, $parents->get($id)?->assigned_id];
            }

            return match ($type) {
                $departmentType => $id,
                $employeeType => $employeeDepartments[$id] ?? null,
                default => null,
            };
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, array{jumlah: int, harga: ?int, nilai: ?int}>
     */
    private function groupedBy(Collection $rows, string $key): array
    {
        return $rows->groupBy($key)->sortKeys()->map(fn (Collection $group): array => $this->summarize($group))->all();
    }

    /**
     * Harga dan nilai null kalau tidak satu aset pun punya datanya, supaya tidak terbaca sebagai Rp 0.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{jumlah: int, harga: ?int, nilai: ?int}
     */
    private function summarize(Collection $rows): array
    {
        $sum = fn (string $key): ?int => $rows->whereNotNull($key)->isEmpty() ? null : (int) $rows->sum($key);

        return [
            'jumlah' => $rows->count(),
            'harga' => $sum('harga'),
            'nilai' => $sum('nilai'),
        ];
    }
}
