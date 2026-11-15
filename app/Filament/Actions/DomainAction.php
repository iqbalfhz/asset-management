<?php

namespace App\Filament\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use LogicException;

// Aturan bisnis di model menolak lewat LogicException; di layar pesannya tampil sebagai notifikasi dan aksinya dihentikan.
class DomainAction
{
    /**
     * Jalankan aturan bisnis; kalau ditolak, tampilkan alasannya lalu hentikan lewat $halt (yang melempar Halt milik Filament).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function attempt(callable $callback, string $failureTitle, Closure $halt): mixed
    {
        try {
            return $callback();
        } catch (LogicException $exception) {
            Notification::make()->danger()->title($failureTitle)->body($exception->getMessage())->send();
            $halt();

            // $halt selalu melempar Halt; return ini hanya memenuhi tipe kembalian.
            return null;
        }
    }

    /**
     * Seperti attempt() untuk tombol aksi, ditambah notifikasi berhasil.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(Action $action, callable $callback, string $successTitle, ?string $failureTitle = null): mixed
    {
        $result = static::attempt($callback, $failureTitle ?? __('Aksi dibatalkan'), fn () => $action->halt());

        Notification::make()->success()->title($successTitle)->send();

        return $result;
    }
}
