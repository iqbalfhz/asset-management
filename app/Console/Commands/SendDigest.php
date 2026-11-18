<?php

namespace App\Console\Commands;

use App\Enums\DigestFrequency;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\Employee;
use App\Models\License;
use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Notifications\Notice;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

// Ringkasan email sesuai pilihan tiap user di Profil (mingguan, bulanan, tahunan), sebatas hak lihatnya.
class SendDigest extends Command
{
    protected $signature = 'notifikasi:ringkasan';

    protected $description = 'Kirim email ringkasan ke user yang jadwal ringkasannya jatuh hari ini';

    // Baris per daftar dibatasi supaya email tetap pendek; sisanya dilihat di aplikasi.
    private const int MAX_LINES = 10;

    public function handle(): void
    {
        $dueFrequencies = collect(DigestFrequency::cases())->filter(fn (DigestFrequency $frequency): bool => $frequency->isDueOn(today()));
        $managingRoleIds = $this->managingRoleIds();

        User::withoutGlobalScope(CompanyScope::class)
            ->whereIn('digest_frequency', $dueFrequencies->map->value)
            ->with(['roles', 'employee'])
            ->get()
            ->filter(fn (User $user): bool => $user->canReceiveEmail())
            ->each(function (User $user) use ($managingRoleIds): void {
                // Query di bawah memakai hak lihat penerima, sama seperti dashboard-nya.
                Auth::setUser($user);
                $periodEnd = $user->digest_frequency->periodEnd(today());

                $sections = array_filter([
                    ...($user->roles->pluck('id')->intersect($managingRoleIds)->isNotEmpty() ? $this->managerSections($periodEnd) : []),
                    ...($user->employee ? $this->employeeSections($user->employee, $periodEnd) : []),
                ]);

                if ($sections !== []) {
                    $user->notifyNow(Notice::digest($user->digest_frequency, $sections, $user->homePanel()?->getUrl() ?? url('/')));
                }
            });

        Auth::forgetUser();
    }

    /**
     * Role yang mengelola kategori aset, lisensi, atau barang habis pakai.
     *
     * @return Collection<int, int>
     */
    private function managingRoleIds(): Collection
    {
        return Category::query()->pluck('role_id')
            ->merge(License::withoutGlobalScopes()->pluck('role_id'))
            ->merge(Consumable::withoutGlobalScopes()->pluck('role_id'))
            ->unique()
            ->values();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function managerSections(CarbonInterface $periodEnd): array
    {
        $warranty = $this->until(Asset::WARRANTY_WARNING_DAYS, $periodEnd);
        $license = $this->until(License::EXPIRY_WARNING_DAYS, $periodEnd);
        $eol = $this->until(Asset::EOL_WARNING_DAYS, $periodEnd);
        $audit = $this->until(Asset::AUDIT_WARNING_DAYS, $periodEnd);
        $return = $this->until(Assignment::RETURN_WARNING_DAYS, $periodEnd);

        return [
            __('Garansi habis sampai :tanggal', ['tanggal' => $this->date($warranty)]) => $this->lines(
                Asset::query()->warrantyEndingSoon($warranty)->orderBy('garansi_sampai'),
                fn (Asset $asset): string => Assignment::labelFor($asset).': '.$this->date($asset->garansi_sampai),
            ),
            __('Lisensi habis sampai :tanggal', ['tanggal' => $this->date($license)]) => $this->lines(
                License::query()->expiringSoon($license)->orderBy('tgl_habis'),
                fn (License $item): string => "{$item->nama_software}: ".$this->date($item->tgl_habis),
            ),
            __('Stok menipis') => $this->lines(
                Consumable::query()->lowStock()->orderBy('stok'),
                fn (Consumable $consumable): string => __(':nama: sisa :stok :satuan (minimum :minimum)', [
                    'nama' => $consumable->nama,
                    'stok' => $consumable->stok,
                    'satuan' => $consumable->satuan,
                    'minimum' => $consumable->stok_minimum,
                ]),
            ),
            __('EOL sampai :tanggal', ['tanggal' => $this->date($eol)]) => $this->lines(
                Asset::query()->eolSoon($eol)->orderBy('eol_date'),
                fn (Asset $asset): string => Assignment::labelFor($asset).': '.$this->date($asset->eol_date),
            ),
            __('Audit jatuh tempo sampai :tanggal', ['tanggal' => $this->date($audit)]) => $this->lines(
                Asset::query()->auditDueSoon($audit)->orderBy('next_audit_date'),
                fn (Asset $asset): string => Assignment::labelFor($asset).': '.$this->date($asset->next_audit_date),
            ),
            __('Pinjaman jatuh tempo sampai :tanggal', ['tanggal' => $this->date($return)]) => $this->lines(
                Assignment::query()->returnDueSoon($return)->with('asset')->orderBy('expected_return_at'),
                fn (Assignment $assignment): string => __(':aset, dipinjam :pemegang: kembali :tanggal', [
                    'aset' => Assignment::labelFor($assignment->asset),
                    'pemegang' => $assignment->targetLabel(),
                    'tanggal' => $this->date($assignment->expected_return_at),
                ]),
            ),
            __('Serah-terima belum diterima lebih dari :hari hari', ['hari' => Assignment::UNACCEPTED_REMINDER_DAYS]) => $this->lines(
                Assignment::query()->unacceptedTooLong()->with('asset')->orderBy('created_at'),
                fn (Assignment $assignment): string => "{$assignment->no_bast}: ".Assignment::labelFor($assignment->asset).', '.$assignment->targetLabel(),
            ),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function employeeSections(Employee $employee, CarbonInterface $periodEnd): array
    {
        $asset = ['asset' => fn (BelongsTo $query) => $query->withoutGlobalScopes()];
        $return = $this->until(Assignment::RETURN_WARNING_DAYS, $periodEnd);

        return [
            __('Serah-terima menunggu Anda terima') => $this->lines(
                Assignment::query()->waitingFor($employee)->with($asset)->orderBy('id'),
                fn (Assignment $assignment): string => Assignment::labelFor($assignment->asset)." (BAST {$assignment->no_bast})",
            ),
            __('Pinjaman Anda yang jatuh tempo sampai :tanggal', ['tanggal' => $this->date($return)]) => $this->lines(
                Assignment::withoutGlobalScope('asset')
                    ->where('target_type', $employee->getMorphClass())
                    ->where('target_id', $employee->getKey())
                    ->returnDueSoon($return)
                    ->with($asset)
                    ->orderBy('expected_return_at'),
                fn (Assignment $assignment): string => Assignment::labelFor($assignment->asset).': '.__('kembali :tanggal', [
                    'tanggal' => $this->date($assignment->expected_return_at),
                ]),
            ),
        ];
    }

    // Batas daftar: yang lebih jauh antara masa peringatan bawaan dan akhir periode ringkasan.
    private function until(int $warningDays, CarbonInterface $periodEnd): CarbonInterface
    {
        return today()->addDays($warningDays)->max($periodEnd);
    }

    private function date(CarbonInterface $date): string
    {
        return $date->translatedFormat('j M Y');
    }

    /**
     * @param  Closure(mixed): string  $line
     * @return array<int, string>
     */
    private function lines(Builder $query, Closure $line): array
    {
        $total = $query->count();
        $lines = $query->limit(self::MAX_LINES)->get()->map($line)->all();

        if ($total > count($lines)) {
            $lines[] = __('dan :count lainnya', ['count' => $total - count($lines)]);
        }

        return $lines;
    }
}
