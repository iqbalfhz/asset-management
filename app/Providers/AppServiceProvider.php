<?php

namespace App\Providers;

use App\Enums\LoginResult;
use App\Http\Responses\LoginResponse;
use App\Livewire\ApiTokens;
use App\Livewire\PersonalInfo;
use App\Models\LoginLog;
use App\Models\User;
use App\Policies\ExportPolicy;
use App\Policies\ImportPolicy;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\ImportAction;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use LogicException;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12)->letters()->numbers());

        // Produksi selalu lewat HTTPS (Cloudflare); link yang dibuat aplikasi ikut HTTPS.
        URL::forceHttps($this->app->isProduction());

        // Breezy mendaftarkan nama komponennya sendiri; arahkan ke versi kita (email boleh kosong, token wajib kedaluwarsa).
        Livewire::component('personal_info', PersonalInfo::class);
        Livewire::component('sanctum_tokens', ApiTokens::class);

        // Semua email Kertas: nama aplikasi dan huruf yang ditulis inline (Outlook tidak mewarisi huruf ke tabel di dalamnya).
        View::composer('mail.*', fn (ViewContract $view) => $view->with([
            'appName' => config('app.name'),
            'sans' => "'Plus Jakarta Sans','Segoe UI',Arial,sans-serif",
        ]));

        $this->explainDeletingRecordsStillInUse();
        $this->limitImports();
        $this->protectExports();
        $this->recordLogins();
        $this->guardApi();

        // Penyedia login Microsoft (Entra ID) dari SocialiteProviders.
        Event::listen(fn (SocialiteWasCalled $event) => $event->extendSocialite('microsoft', MicrosoftProvider::class));

        // Halaman Backup: hanya Super Admin yang mengunduh; backup dihapus oleh pembersihan terjadwal, bukan dari panel.
        Gate::define('download-backup', fn (User $user): bool => $user->hasRole('super_admin'));
        Gate::define('delete-backup', fn (): bool => false);
    }

    // REST API: token hanya berlaku untuk akun aktif yang boleh membuka panel admin, dan permintaan dibatasi per menit.
    private function guardApi(): void
    {
        Sanctum::authenticateAccessTokensUsing(fn (PersonalAccessToken $token, bool $isValid): bool => $isValid
            && $token->tokenable instanceof User
            && $token->tokenable->canAccessPanel(Filament::getPanel('admin')));

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) ($request->user('sanctum')?->getAuthIdentifier() ?? $request->ip())));
    }

    // Log masuk untuk Super Admin; "terkunci" dicatat oleh halaman login saat batas percobaan tercapai.
    private function recordLogins(): void
    {
        Event::listen(Login::class, fn (Login $event) => LoginLog::record(LoginResult::Berhasil, $event->user));
        Event::listen(Logout::class, fn (Logout $event) => LoginLog::record(LoginResult::Keluar, $event->user));
        Event::listen(Failed::class, fn (Failed $event) => LoginLog::record(
            LoginResult::Gagal,
            $event->user,
            $event->credentials['email'] ?? $event->credentials['username'] ?? null,
        ));
    }

    private function protectExports(): void
    {
        // Hasil export dibuka di Excel; isi yang diawali =, +, -, @ jangan sampai dijalankan sebagai rumus.
        ExportColumn::configureUsing(fn (ExportColumn $column) => $column->preventFormulaInjection());

        Gate::policy(Export::class, ExportPolicy::class);
    }

    private function limitImports(): void
    {
        ImportAction::configureUsing(fn (ImportAction $action) => $action
            ->maxRows(5000)
            ->fileRules(['max:5120']));

        // CSV baris gagal dibuka di Excel; isi yang diawali =, +, -, @ jangan sampai dijalankan sebagai rumus.
        Importer::preventFormulaInjection();

        Gate::policy(Import::class, ImportPolicy::class);
    }

    /**
     * Database atau model menolak menghapus data yang masih dirujuk data lain; tampilkan pesan, bukan halaman error.
     */
    private function explainDeletingRecordsStillInUse(): void
    {
        DeleteAction::configureUsing(fn (DeleteAction $action) => $action->using(function (Model $record): bool {
            $reason = __('Data ini masih dipakai oleh data lain. Ubah atau hapus dulu data yang memakainya.');

            try {
                // false = ditolak model, misalnya karyawan yang punya riwayat serah-terima.
                if ($record->delete()) {
                    return true;
                }
            } catch (LogicException $exception) {
                // Aturan bisnis di model menyebut alasannya sendiri, misalnya lisensi yang pernah dipakai.
                $reason = $exception->getMessage();
            } catch (QueryException $exception) {
                // 23000 (MySQL/SQLite) dan 23503 (PostgreSQL): pelanggaran foreign key.
                if (! in_array($exception->getCode(), ['23000', '23503'], true)) {
                    throw $exception;
                }
            }

            Notification::make()
                ->danger()
                ->title(__('Data tidak bisa dihapus'))
                ->body($reason)
                ->send();

            return false;
        }));

        DeleteBulkAction::configureUsing(fn (DeleteBulkAction $action) => $action->missingBulkProcessingFailureNotificationMessage(
            fn (int $failureCount): string => __(':count data tidak bisa dihapus karena masih dipakai oleh data lain.', ['count' => $failureCount]),
        ));
    }
}
