<?php

namespace App\Http\Controllers;

use App\Enums\LoginResult;
use App\Models\LoginLog;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// Masuk dengan akun Microsoft 365 kantor (Entra ID): hanya untuk akun yang sudah terdaftar dan aktif, tidak membuat akun baru.
class MicrosoftLoginController extends Controller
{
    public static function isConfigured(): bool
    {
        return filled(config('services.microsoft.client_id'))
            && filled(config('services.microsoft.client_secret'))
            && filled(config('services.microsoft.tenant'));
    }

    public function redirect(): Response
    {
        abort_unless(static::isConfigured(), 404);

        return Socialite::driver('microsoft')->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback(Request $request): Response
    {
        abort_unless(static::isConfigured(), 404);

        if ($request->has('error')) {
            return $this->backToLogin(__('Masuk dengan Microsoft dibatalkan.'));
        }

        try {
            $account = Socialite::driver('microsoft')->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->backToLogin(__('Masuk dengan Microsoft gagal. Silakan coba lagi.'));
        }

        $user = User::forMicrosoftAccount($account->getId(), [$account->getEmail(), Arr::get($account->getRaw(), 'mail')]);

        if (! $user?->canAccessPanel(Filament::getPanel('auth'))) {
            LoginLog::record(LoginResult::Gagal, $user, $account->getEmail());

            return $this->backToLogin(__('Akun Microsoft :email belum terdaftar atau tidak aktif di aplikasi ini. Hubungi admin.', ['email' => $account->getEmail()]));
        }

        $user->microsoft_id ??= $account->getId();
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return app(LoginResponse::class)->toResponse($request);
    }

    private function backToLogin(string $message): RedirectResponse
    {
        Notification::make()->danger()->title($message)->persistent()->send();

        return redirect()->route('filament.auth.auth.login');
    }
}
