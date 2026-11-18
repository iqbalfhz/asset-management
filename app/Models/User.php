<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\LogsChanges;
use App\Models\Scopes\CompanyScope;
use Database\Factories\UserFactory;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Jeffgreco13\FilamentBreezy\Traits\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'email', 'avatar_url', 'password', 'company_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
#[ScopedBy([CompanyScope::class])]
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsChanges, Notifiable, TwoFactorAuthenticatable;

    // Admin juga karyawan yang memegang aset, jadi role admin ikut membuka panel karyawan; panel auth hanya halaman masuk.
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $panels = $this->roles->pluck('panel');

        return match ($panel->getId()) {
            'admin' => $panels->contains('admin'),
            'karyawan', 'auth' => $panels->contains('admin') || $panels->contains('karyawan'),
            default => false,
        };
    }

    // Panel tujuan setelah masuk: admin kalau boleh, selain itu karyawan.
    public function homePanel(): ?Panel
    {
        foreach (['admin', 'karyawan'] as $panelId) {
            $panel = Filament::getPanel($panelId);

            if ($this->canAccessPanel($panel)) {
                return $panel;
            }
        }

        return null;
    }

    // Perusahaan yang boleh dilihat user ini, atau null kalau datanya tidak dibatasi.
    public function restrictedCompanyId(): ?int
    {
        return Setting::get('company_separation', false) && ! $this->can('view_all_companies') ? $this->company_id : null;
    }

    public function canReceiveEmail(): bool
    {
        return $this->is_active && filled($this->email);
    }

    /**
     * Akun untuk login Microsoft: yang sudah tertaut ke ID itu, atau yang belum tertaut dan emailnya sama dengan email kantornya.
     *
     * @param  array<int, ?string>  $emails
     */
    public static function forMicrosoftAccount(string $microsoftId, array $emails): ?self
    {
        $emails = collect($emails)->filter()->map(fn (string $email): string => Str::lower($email))->unique()->all();

        return static::withoutGlobalScopes()->firstWhere('microsoft_id', $microsoftId)
            ?? static::withoutGlobalScopes()->whereNull('microsoft_id')->whereIn(DB::raw('lower(email)'), $emails)->first();
    }

    /**
     * Admin pengelola yang menerima email: pemegang role pengelola itu yang boleh melihat perusahaan datanya.
     *
     * @return Collection<int, self>
     */
    public static function managersOf(int $roleId, int $companyId): Collection
    {
        return static::withoutGlobalScope(CompanyScope::class)
            ->whereHas('roles', fn (Builder $query) => $query->whereKey($roleId))
            ->get()
            ->filter(fn (self $user): bool => $user->canReceiveEmail() && in_array($user->restrictedCompanyId(), [null, $companyId], true))
            ->values();
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Data karyawan milik akun ini; panel karyawan selalu dibatasi ke sini.
     *
     * @return HasOne<Employee, $this>
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    // Foto profil ada di disk privat, jadi ditampilkan lewat link sementara yang ditandatangani.
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ? Storage::disk('local')->temporaryUrl($this->avatar_url, now()->addMinutes(30)) : null;
    }

    // Email boleh kosong, jadi label di aplikasi authenticator memakai username sebagai cadangan.
    public function getTwoFactorQrCodeUrl(): string
    {
        return filament('filament-breezy')->getQrCodeUrl(
            config('app.name'),
            $this->email ?? $this->username,
            $this->breezySession->two_factor_secret,
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
