<?php

namespace App\Http\Controllers;

use App\Enums\DisposalStatus;
use App\Models\Disposal;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DownloadDisposalReportController extends Controller
{
    /**
     * Berita acara hanya untuk ajuan yang disetujui, dan hanya untuk yang boleh melihat asetnya.
     */
    public function __invoke(int $disposal): Response
    {
        // Global scope penghapusan mengikuti hak lihat aset, jadi aset yang tidak boleh dilihat terbaca 404.
        $disposal = Disposal::query()->with(['asset.company', 'asset.category', 'submitter', 'decider'])->findOrFail($disposal);

        abort_unless($disposal->status === DisposalStatus::Disetujui, 404);
        Gate::authorize('view', $disposal);

        $company = $disposal->asset->company;

        return Pdf::loadView('pdf.berita-acara-penghapusan', [
            'disposal' => $disposal,
            'company' => $company,
            'kop' => Setting::get('kop_bast', null, $company->id),
            'logo' => Setting::logoDataUri($company->id),
        ])->setPaper('a4')->download(Str::of($disposal->no_berita_acara)->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-').'.pdf');
    }
}
