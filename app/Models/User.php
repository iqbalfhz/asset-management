<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Scopes\CompanyScope;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Jeffgreco13\FilamentBreezy\Traits\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'email', 'avatar_url', 'password', 'company_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
#[ScopedBy([CompanyScope::class])]
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active
            && $this->roles->contains('panel', $panel->getId());
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
