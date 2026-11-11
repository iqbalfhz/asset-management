<?php

namespace App\Http\Controllers;

use App\Enums\AuditResult;
use App\Models\Audit;
use App\Models\AuditItem;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DownloadAuditReportController extends Controller
{
    /**
     * Laporan hasil stock opname; sesi dan asetnya mengikuti hak lihat pengunduh.
     */
    public function __invoke(int $audit): Response
    {
        // Global scope sesi mengikuti role pengelola dan perusahaan ruang, jadi sesi milik role lain terbaca 404.
        $audit = Audit::query()->with(['room.floor.building.company', 'creator', 'closer'])->findOrFail($audit);

        Gate::authorize('view', $audit);

        $items = $audit->items()
            ->with(['asset.category', 'recordedRoom'])
            ->get()
            ->sortBy(fn (AuditItem $item): string => $item->asset->kode)
            ->groupBy(fn (AuditItem $item): string => $item->hasil->value);

        $company = $audit->room->floor->building->company;

        return Pdf::loadView('pdf.laporan-stock-opname', [
            'audit' => $audit,
            'items' => $items,
            'results' => AuditResult::cases(),
            'company' => $company,
            'kop' => Setting::get('kop_bast', null, $company->id),
            'logo' => Setting::logoDataUri($company->id),
        ])->setPaper('a4')->download(Str::of("Stock opname {$audit->room->nama} {$audit->mulai_pada->format('Y-m-d')}")->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-').'.pdf');
    }
}
