<?php

namespace App\Console\Commands;

use App\Models\AssetReminder;
use App\Models\User;
use App\Notifications\Notice;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SendReminderEmails extends Command
{
    protected $signature = 'pengingat:kirim';

    protected $description = 'Kirim email pengingat tanggal penting aset H-30 dan H-7';

    public function handle(): void
    {
        AssetReminder::withoutGlobalScopes()
            ->whereNull('selesai_pada')
            ->where(function (Builder $query): void {
                foreach (AssetReminder::EMAIL_DAYS_BEFORE as $days) {
                    $query->orWhereDate('tanggal', today()->addDays($days));
                }
            })
            ->with(['asset' => fn (BelongsTo $query) => $query->withoutGlobalScopes()->with('category')])
            ->get()
            ->each(fn (AssetReminder $reminder) => User::managersOf($reminder->asset->category->role_id, $reminder->asset->company_id)
                ->each(fn (User $user) => $user->notifyNow($user->inOwnLanguage(fn (): Notice => Notice::reminderDue($reminder, $reminder->asset)))));
    }
}
