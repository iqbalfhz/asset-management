<?php

namespace App\Providers;

use App\Livewire\PersonalInfo;
use App\Policies\ImportPolicy;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ImportAction;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12)->letters()->numbers());

        // Breezy mendaftarkan nama 'personal_info' untuk komponennya; arahkan ke versi kita (email boleh kosong).
        Livewire::component('personal_info', PersonalInfo::class);

        $this->explainDeletingRecordsStillInUse();
        $this->limitImports();
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
     * Database menolak menghapus data yang masih dirujuk data lain; tampilkan pesan, bukan halaman error.
     */
    private function explainDeletingRecordsStillInUse(): void
    {
        DeleteAction::configureUsing(fn (DeleteAction $action) => $action->using(function (Model $record): bool {
            try {
                return (bool) $record->delete();
            } catch (QueryException $exception) {
                // 23000 (MySQL/SQLite) dan 23503 (PostgreSQL): pelanggaran foreign key.
                if (! in_array($exception->getCode(), ['23000', '23503'], true)) {
                    throw $exception;
                }

                Notification::make()
                    ->danger()
                    ->title(__('Data tidak bisa dihapus'))
                    ->body(__('Data ini masih dipakai oleh data lain. Ubah atau hapus dulu data yang memakainya.'))
                    ->send();

                return false;
            }
        }));

        DeleteBulkAction::configureUsing(fn (DeleteBulkAction $action) => $action->missingBulkProcessingFailureNotificationMessage(
            fn (int $failureCount): string => __(':count data tidak bisa dihapus karena masih dipakai oleh data lain.', ['count' => $failureCount]),
        ));
    }
}
