<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\Employee;
use App\Models\License;
use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Notifications\Notice;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

// Ringkasan harian: admin pengelola dan karyawan menerima hal yang perlu ditindaklanjuti, sebatas hak lihatnya.
class SendDailyDigest extends Command
{
    protected $signature = 'notifikasi:harian';

    protected $description = 'Kirim email ringkasan harian ke admin pengelola dan karyawan';

    // Baris per daftar dibatasi supaya email tetap pendek; sisanya dilihat di aplikasi.
    private const int MAX_LINES = 10;

    public function handle(): void
    {
        $managingRoleIds = $this->managingRoleIds();

        User::withoutGlobalScope(CompanyScope::class)->with(['roles', 'employee'])->get()
            ->filter(fn (User $user): bool => $user->canReceiveEmail())
            ->each(function (User $user) use ($managingRoleIds): void {
                // Query di bawah memakai hak lihat penerima, sama seperti dashboard-nya.
                Auth::setUser($user);

                $sections = array_filter([
                    ...($user->roles->pluck('id')->intersect($managingRoleIds)->isNotEmpty() ? $this->managerSections() : []),
                    ...($user->employee ? $this->employeeSections($user->employee) : []),
                ]);

                if ($sections !== []) {
                    $user->notifyNow(Notice::dailyDigest($sections, $user->homePanel()?->getUrl() ?? url('/')));
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
    private function managerSections(): array
    {
        return [
            __('Garansi habis dalam :hari hari', ['hari' => Asset::WARRANTY_WARNING_DAYS]) => $this->lines(
                Asset::query()->warrantyEndingSoon()->orderBy('garansi_sampai'),
                fn (Asset $asset): string => Assignment::labelFor($asset).': '.$asset->garansi_sampai->translatedFormat('j M Y'),
            ),
            __('Lisensi habis dalam :hari hari', ['hari' => License::EXPIRY_WARNING_DAYS]) => $this->lines(
                License::query()->expiringSoon()->orderBy('tgl_habis'),
                fn (License $license): string => "{$license->nama_software}: ".$license->tgl_habis->translatedFormat('j M Y'),
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
            __('EOL dalam :hari hari', ['hari' => Asset::EOL_WARNING_DAYS]) => $this->lines(
                Asset::query()->eolSoon()->orderBy('eol_date'),
                fn (Asset $asset): string => Assignment::labelFor($asset).': '.$asset->eol_date->translatedFormat('j M Y'),
            ),
            __('Audit jatuh tempo') => $this->lines(
                Asset::query()->auditDueSoon()->orderBy('next_audit_date'),
                fn (Asset $asset): string => Assignment::labelFor($asset).': '.$asset->next_audit_date->translatedFormat('j M Y'),
            ),
            __('Pinjaman jatuh tempo') => $this->lines(
                Assignment::query()->returnDueSoon()->with('asset')->orderBy('expected_return_at'),
                fn (Assignment $assignment): string => __(':aset, dipinjam :pemegang: kembali :tanggal', [
                    'aset' => Assignment::labelFor($assignment->asset),
                    'pemegang' => $assignment->targetLabel(),
                    'tanggal' => $assignment->expected_return_at->translatedFormat('j M Y'),
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
    private function employeeSections(Employee $employee): array
    {
        $asset = ['asset' => fn (BelongsTo $query) => $query->withoutGlobalScopes()];

        return [
            __('Serah-terima menunggu Anda terima') => $this->lines(
                Assignment::query()->waitingFor($employee)->with($asset)->orderBy('id'),
                fn (Assignment $assignment): string => Assignment::labelFor($assignment->asset)." (BAST {$assignment->no_bast})",
            ),
            __('Pinjaman Anda yang jatuh tempo') => $this->lines(
                Assignment::withoutGlobalScope('asset')
                    ->where('target_type', $employee->getMorphClass())
                    ->where('target_id', $employee->getKey())
                    ->returnDueSoon()
                    ->with($asset)
                    ->orderBy('expected_return_at'),
                fn (Assignment $assignment): string => Assignment::labelFor($assignment->asset).': '.__('kembali :tanggal', [
                    'tanggal' => $assignment->expected_return_at->translatedFormat('j M Y'),
                ]),
            ),
        ];
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
