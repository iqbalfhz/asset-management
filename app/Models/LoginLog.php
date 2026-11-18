<?php

namespace App\Models;

use App\Enums\LoginResult;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Locale;

#[Fillable(['hasil', 'user_id', 'login', 'ip_address', 'negara', 'kota', 'user_agent', 'created_at'])]
class LoginLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /**
     * Catat satu kejadian login. Negara dan kota dari header Cloudflare; tanpa Cloudflare (lokal) keduanya kosong.
     */
    public static function record(LoginResult $result, ?Authenticatable $user, ?string $typed = null): void
    {
        $request = request();
        $country = strtoupper((string) $request->header('CF-IPCountry'));

        static::query()->create([
            'hasil' => $result,
            'user_id' => $user?->getAuthIdentifier(),
            'login' => $user ? ($typed ?? $user->getAttribute('username')) : static::mask($typed),
            'ip_address' => $request->ip(),
            'negara' => preg_match('/^[A-Z0-9]{2}$/', $country) ? $country : null,
            'kota' => Str::limit(trim((string) $request->header('CF-IPCity')), 100, '') ?: null,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, '') ?: null,
            'created_at' => now(),
        ]);
    }

    // Isian tanpa akun yang cocok bisa saja password yang salah kolom, jadi hanya dua huruf pertamanya yang disimpan.
    private static function mask(?string $typed): ?string
    {
        return blank($typed) ? null : Str::substr($typed, 0, 2).'•••';
    }

    // Nama negara dalam Bahasa Indonesia; T1 = jaringan Tor, XX = tidak diketahui Cloudflare.
    public function countryName(): ?string
    {
        return match ($this->negara) {
            null, 'XX' => null,
            'T1' => __('Jaringan Tor'),
            default => Locale::getDisplayRegion("-{$this->negara}", 'id'),
        };
    }

    // Ringkasan perangkat dari user agent, contoh "Chrome · Windows".
    public function device(): ?string
    {
        if ($this->user_agent === null) {
            return null;
        }

        $browser = collect(['Edg/' => 'Edge', 'OPR/' => 'Opera', 'Firefox/' => 'Firefox', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'])
            ->first(fn (string $name, string $marker): bool => str_contains($this->user_agent, $marker));
        $system = collect(['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS' => 'macOS', 'Linux' => 'Linux'])
            ->first(fn (string $name, string $marker): bool => str_contains($this->user_agent, $marker));

        return collect([$browser, $system])->filter()->implode(' · ') ?: __('Lainnya');
    }

    // Disimpan satu tahun.
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subYear());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hasil' => LoginResult::class,
            'created_at' => 'datetime',
        ];
    }
}
