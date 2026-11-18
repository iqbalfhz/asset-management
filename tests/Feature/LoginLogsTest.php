<?php

use App\Enums\LoginResult;
use App\Filament\Pages\LoginLogs;
use App\Models\LoginLog;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
});

test('the country and city come from the Cloudflare headers', function () {
    request()->headers->set('CF-IPCountry', 'US');
    request()->headers->set('CF-IPCity', 'Ashburn');

    LoginLog::record(LoginResult::Gagal, null, 'tamu');

    $entry = LoginLog::query()->sole();

    expect($entry->negara)->toBe('US')
        ->and($entry->kota)->toBe('Ashburn')
        ->and($entry->countryName())->toBe('Amerika Serikat');
});

test('only a super admin opens the login log, which shows where each attempt came from', function () {
    request()->headers->set('CF-IPCountry', 'US');
    request()->headers->set('CF-IPCity', 'Ashburn');
    LoginLog::record(LoginResult::Gagal, null, 'tamu');

    $this->actingAs(userWithRole('admin_it'))->get(LoginLogs::getUrl())->assertForbidden();

    $this->actingAs(userWithRole('super_admin'));

    $this->get(LoginLogs::getUrl())->assertOk();
    livewire(LoginLogs::class)->assertSee('Ashburn, Amerika Serikat');
});

test('entries older than a year are pruned', function () {
    LoginLog::record(LoginResult::Berhasil, null);
    LoginLog::query()->update(['created_at' => now()->subYear()->subDay()]);
    LoginLog::record(LoginResult::Berhasil, null);

    $this->artisan('model:prune', ['--model' => [LoginLog::class]])->assertSuccessful();

    expect(LoginLog::query()->count())->toBe(1);
});
