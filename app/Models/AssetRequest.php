<?php

namespace App\Models;

use App\Enums\AssetRequestStatus;
use App\Models\Concerns\LocksStatus;
use App\Models\Concerns\LogsChanges;
use App\Notifications\Notice;
use Database\Factories\AssetRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use LogicException;

#[Fillable(['employee_id', 'category_id', 'alasan', 'status', 'catatan', 'diputuskan_oleh', 'assignment_id'])]
class AssetRequest extends Model
{
    /** @use HasFactory<AssetRequestFactory> */
    use HasFactory, LocksStatus, LogsChanges;

    protected static function booted(): void
    {
        // Admin melihat permintaan untuk kategori yang dikelolanya, dari karyawan di perusahaan yang boleh dilihatnya.
        static::addGlobalScope('managed', fn (Builder $query) => $query
            ->whereHas('employee')
            ->whereHas('category', fn (Builder $query) => $query->managedByCurrentUser()));
    }

    /**
     * Karyawan meminta aset dari kategori yang punya aset bertanda Boleh diminta.
     */
    public static function submit(Employee $employee, Category $category, string $alasan): self
    {
        return DB::transaction(function () use ($employee, $category, $alasan): self {
            // Baris karyawan dikunci, supaya dua kiriman bersamaan tidak membuat dua permintaan terbuka.
            $employee = Employee::withoutGlobalScopes()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();
            $employee->ensureActive();

            if (! Category::query()->requestableIn($employee->company_id)->whereKey($category->getKey())->exists()) {
                throw new LogicException(__('Kategori :kategori tidak bisa diminta.', ['kategori' => $category->nama]));
            }

            $hasOpenRequest = static::withoutGlobalScopes()
                ->where('employee_id', $employee->getKey())
                ->where('category_id', $category->getKey())
                ->whereIn('status', AssetRequestStatus::open())
                ->exists();

            if ($hasOpenRequest) {
                throw new LogicException(__('Anda masih punya permintaan :kategori yang belum selesai.', ['kategori' => $category->nama]));
            }

            $request = static::query()->create([
                'employee_id' => $employee->getKey(),
                'category_id' => $category->getKey(),
                'alasan' => $alasan,
                'status' => AssetRequestStatus::Diajukan,
            ]);

            Notification::send(User::managersOf($category->role_id, $employee->company_id), Notice::assetRequested($employee, $category, $alasan));

            return $request;
        });
    }

    public function approve(?string $catatan = null): void
    {
        $this->decide(AssetRequestStatus::Disetujui, $catatan);
    }

    public function reject(string $catatan): void
    {
        if (blank($catatan)) {
            throw new LogicException(__('Alasan ditolak wajib diisi.'));
        }

        $this->decide(AssetRequestStatus::Ditolak, $catatan);
    }

    /**
     * Serahkan satu aset dari kategori yang diminta; permintaan selesai saat karyawan menekan Terima.
     *
     * @param  array{tanggal: string, kondisi: string, catatan?: ?string, expected_return_at?: ?string}  $details
     */
    public function handOver(Asset $asset, array $details): string
    {
        return DB::transaction(function () use ($asset, $details): string {
            $this->ensureStatus(AssetRequestStatus::Disetujui);

            if ($this->isWaitingForAcceptance()) {
                throw new LogicException(__('Aset untuk permintaan ini sudah diserahkan dan menunggu diterima karyawan.'));
            }

            if ($asset->category_id !== $this->category_id) {
                throw new LogicException(__('Aset :kode bukan dari kategori yang diminta.', ['kode' => $asset->kode]));
            }

            $noBast = Assignment::handOver(new Collection([$asset]), $this->employee, $details);

            $this->update(['assignment_id' => Assignment::query()->where('no_bast', $noBast)->value('id')]);

            return $noBast;
        });
    }

    // Aset sudah diserahkan untuk permintaan ini dan karyawan belum menekan Terima.
    public function isWaitingForAcceptance(): bool
    {
        return $this->assignment_id !== null
            && Assignment::query()->waitingFor($this->employee)->whereKey($this->assignment_id)->exists();
    }

    /**
     * Karyawan menekan Terima: permintaan yang asetnya ada di serah-terima itu selesai.
     *
     * @param  array<int, int>  $assignmentIds
     */
    public static function completeFor(array $assignmentIds): void
    {
        static::withoutGlobalScopes()
            ->whereIn('assignment_id', $assignmentIds)
            ->where('status', AssetRequestStatus::Disetujui)
            ->get()
            ->each(fn (self $request) => $request->update(['status' => AssetRequestStatus::Selesai]));
    }

    private function decide(AssetRequestStatus $status, ?string $catatan): void
    {
        DB::transaction(function () use ($status, $catatan): void {
            $this->ensureStatus(AssetRequestStatus::Diajukan);

            $this->update(['status' => $status, 'catatan' => $catatan, 'diputuskan_oleh' => auth()->id()]);

            $requester = $this->employee->user;

            if ($requester?->canReceiveEmail()) {
                $requester->notify(Notice::assetRequestDecided($this));
            }
        });
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Admin yang menyetujui atau menolak.
     *
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }

    /**
     * Serah-terima aset untuk permintaan ini.
     *
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssetRequestStatus::class,
        ];
    }
}
