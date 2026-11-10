<?php

namespace App\Providers;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        $this->explainDeletingRecordsStillInUse();
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
