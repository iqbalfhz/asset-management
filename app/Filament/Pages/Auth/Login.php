<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends BaseLogin
{
    // Batas percobaan gagal per menit untuk satu akun dari satu alamat IP.
    private const int MAX_FAILED_ATTEMPTS = 5;

    // Detik sampai boleh mencoba lagi; selama lebih dari 0, tombol Masuk terkunci dan menghitung mundur di browser.
    public int $lockedSeconds = 0;

    #[Exposed]
    public function authenticate(): ?LoginResponse
    {
        try {
            return parent::authenticate();
        } finally {
            $this->lockedSeconds = $this->secondsUntilUnlocked();
        }
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getLoginFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getLoginFormComponent(): Component
    {
        return TextInput::make('login')
            ->label(__('Username atau email'))
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $column = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [
            $column => $data['login'],
            'password' => $data['password'],
        ];
    }

    // Pengganti batas bawaan Filament (per IP, semua percobaan dihitung): satu kantor keluar lewat satu IP,
    // jadi batas dihitung per akun dan IP, dan hanya percobaan gagal yang menambah hitungan.
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void
    {
        $seconds = $this->secondsUntilUnlocked();

        if ($seconds > 0) {
            throw new TooManyRequestsException(static::class, 'authenticate', request()->ip(), $seconds);
        }
    }

    protected function throwFailureValidationException(): never
    {
        RateLimiter::hit($this->loginRateLimitKey());

        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    // Batas server tetap berlaku; tombol hanya mencegah klik sia-sia dan menampilkan sisa waktunya.
    protected function getAuthenticateFormAction(): Action
    {
        $label = __('filament-panels::auth/pages/login.form.actions.authenticate.label');
        $waiting = __('Coba lagi dalam :seconds detik');

        return parent::getAuthenticateFormAction()
            ->label(fn (): Htmlable => new HtmlString(sprintf(
                '<span x-text="$wire.lockedSeconds > 0 ? %s.replace(\':seconds\', $wire.lockedSeconds) : %s">%s</span>',
                e(Js::from($waiting)),
                e(Js::from($label)),
                e($this->lockedSeconds > 0 ? __('Coba lagi dalam :seconds detik', ['seconds' => $this->lockedSeconds]) : $label),
            )))
            ->extraAttributes([
                'x-init' => 'setInterval(() => { if ($wire.lockedSeconds > 0) $wire.lockedSeconds-- }, 1000)',
                'x-bind:disabled' => 'isProcessing || $wire.lockedSeconds > 0',
            ]);
    }

    private function secondsUntilUnlocked(): int
    {
        $key = $this->loginRateLimitKey();

        return RateLimiter::tooManyAttempts($key, self::MAX_FAILED_ATTEMPTS) ? RateLimiter::availableIn($key) : 0;
    }

    private function loginRateLimitKey(): string
    {
        return 'login:'.Str::transliterate(Str::lower(trim((string) ($this->data['login'] ?? '')))).'|'.request()->ip();
    }
}
