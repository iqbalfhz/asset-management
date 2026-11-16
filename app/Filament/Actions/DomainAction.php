<?php

namespace App\Filament\Actions;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use LogicException;

// Tombol aksi untuk aturan bisnis di model: penolakan lewat LogicException tampil sebagai notifikasi dan aksinya dihentikan.
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
     * Tombol yang memindahkan data dari satu status: hanya tampil di status itu dan butuh izin ubah.
     */
    public static function transition(string $name, string $label, Heroicon $icon, BackedEnum $from): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->visible(fn (Model $record): bool => $record->getAttribute('status') === $from)
            ->authorize('update')
            ->modalSubmitActionLabel($label);
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
