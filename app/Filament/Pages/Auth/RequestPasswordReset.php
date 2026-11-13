<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

// Lupa password memakai kode 6 digit lewat email: langkah 1 isi email, langkah 2 isi kode dan kata sandi baru.
class RequestPasswordReset extends BaseRequestPasswordReset
{
    public const CODE_MINUTES = 10;

    private const MAX_WRONG_CODES_PER_HOUR = 5;

    private const RESEND_SECONDS = 60;

    // Email yang sedang direset; null berarti masih di langkah 1.
    #[Locked]
    public ?string $email = null;

    #[Exposed]
    public function request(): void
    {
        if ($this->email !== null) {
            $this->resetPassword();

            return;
        }

        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $this->email = Str::lower(trim($this->form->getState()['email']));
        $this->sendCode();
        $this->form->fill();
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        $onCodeStep = fn (): bool => $this->email !== null;

        return $schema
            ->components([
                $this->getEmailFormComponent()
                    ->hidden($onCodeStep),
                Text::make(fn (): string => __('Kalau :email terdaftar, kode 6 digit sudah dikirim ke email tersebut. Biasanya masuk dalam beberapa detik; cek juga folder spam. Kode berlaku :menit menit.', [
                    'email' => $this->email,
                    'menit' => self::CODE_MINUTES,
                ]))
                    ->visible($onCodeStep),
                TextInput::make('code')
                    ->label(__('Kode dari email'))
                    ->required()
                    ->rule('digits:6')
                    ->validationAttribute(__('kode'))
                    ->autocomplete('one-time-code')
                    ->belowContent([
                        Action::make('resendCode')
                            ->label(__('Kirim ulang kode'))
                            ->link()
                            ->action(fn () => $this->resendCode()),
                        Action::make('changeEmail')
                            ->label(__('Ganti email'))
                            ->link()
                            ->color('gray')
                            ->action(function (): void {
                                $this->email = null;
                                $this->form->fill();
                            }),
                    ])
                    ->visible($onCodeStep),
                TextInput::make('password')
                    ->label(__('filament-panels::auth/pages/password-reset/reset-password.form.password.label'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->autocomplete('new-password')
                    ->required()
                    ->rule(PasswordRule::default())
                    ->same('passwordConfirmation')
                    ->validationAttribute(__('filament-panels::auth/pages/password-reset/reset-password.form.password.validation_attribute'))
                    ->visible($onCodeStep),
                TextInput::make('passwordConfirmation')
                    ->label(__('filament-panels::auth/pages/password-reset/reset-password.form.password_confirmation.label'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->autocomplete('new-password')
                    ->required()
                    ->dehydrated(false)
                    ->visible($onCodeStep),
            ]);
    }

    protected function getRequestFormAction(): Action
    {
        return parent::getRequestFormAction()
            ->label(fn (): string => $this->email === null ? __('Kirim kode') : __('Ganti kata sandi'));
    }

    protected function resendCode(): void
    {
        if (! $this->sendCode()) {
            Notification::make()
                ->title(__('Tunggu :detik detik sebelum meminta kode baru.', ['detik' => RateLimiter::availableIn($this->sendKey())]))
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('Kode baru dikirim kalau email ini terdaftar.'))
            ->success()
            ->send();
    }

    // Jeda kirim ulang dan batas kode salah dihitung per email, terdaftar atau tidak, supaya jawabannya selalu sama.
    private function sendCode(): bool
    {
        if (! RateLimiter::attempt($this->sendKey(), 1, fn (): bool => true, self::RESEND_SECONDS)) {
            return false;
        }

        if (RateLimiter::tooManyAttempts($this->wrongCodeKey(), self::MAX_WRONG_CODES_PER_HOUR)) {
            return true;
        }

        $user = User::query()->where('email', $this->email)->first();

        if (! $user?->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            return true;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->upsert(
            ['email' => $this->email, 'token' => $this->hashCode($code), 'created_at' => now()],
            ['email'],
            ['token', 'created_at'],
        );

        $user->notify(new PasswordResetCode($code));

        return true;
    }

    private function resetPassword(): void
    {
        try {
            $this->rateLimit(10);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $data = $this->form->getState();

        if (RateLimiter::tooManyAttempts($this->wrongCodeKey(), self::MAX_WRONG_CODES_PER_HOUR)) {
            throw ValidationException::withMessages([
                'data.code' => __('Terlalu banyak kode salah. Coba lagi dalam :menit menit.', [
                    'menit' => (int) ceil(RateLimiter::availableIn($this->wrongCodeKey()) / 60),
                ]),
            ]);
        }

        $user = $this->userWithValidCode($data['code']);

        if (! $user) {
            RateLimiter::hit($this->wrongCodeKey(), 3600);

            // Kode ikut hangus saat batas tercapai, jadi tebakan berikutnya tidak berguna walau batasnya sudah lewat.
            if (RateLimiter::tooManyAttempts($this->wrongCodeKey(), self::MAX_WRONG_CODES_PER_HOUR)) {
                DB::table('password_reset_tokens')->where('email', $this->email)->delete();
            }

            throw ValidationException::withMessages([
                'data.code' => __('Kode salah atau sudah kedaluwarsa.'),
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $this->email)->delete();
        RateLimiter::clear($this->wrongCodeKey());
        event(new PasswordReset($user));

        Notification::make()
            ->title(__('Kata sandi sudah diganti. Silakan masuk dengan kata sandi baru.'))
            ->success()
            ->send();

        $this->redirect(Filament::getLoginUrl());
    }

    private function userWithValidCode(string $code): ?User
    {
        $record = DB::table('password_reset_tokens')->where('email', $this->email)->first();

        if (
            ! $record
            || Carbon::parse($record->created_at)->addMinutes(self::CODE_MINUTES)->isPast()
            || ! hash_equals($record->token, $this->hashCode($code))
        ) {
            return null;
        }

        $user = User::query()->where('email', $this->email)->first();

        return $user?->canAccessPanel(Filament::getCurrentOrDefaultPanel()) ? $user : null;
    }

    // Kode hanya 6 digit, jadi disimpan sebagai HMAC dengan APP_KEY: tidak bisa ditebak dari isi database saja.
    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function sendKey(): string
    {
        return 'password-reset-send:'.sha1($this->email);
    }

    private function wrongCodeKey(): string
    {
        return 'password-reset-wrong-code:'.sha1($this->email);
    }
}
