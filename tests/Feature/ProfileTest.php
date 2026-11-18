<?php

use App\Enums\DigestFrequency;
use App\Enums\Language;
use App\Livewire\ApiTokens;
use App\Livewire\PersonalInfo;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Jeffgreco13\FilamentBreezy\Livewire\BrowserSessions;
use Jeffgreco13\FilamentBreezy\Livewire\TwoFactorAuthentication;
use Jeffgreco13\FilamentBreezy\Livewire\UpdatePassword;

use function Pest\Livewire\livewire;

test('the admin profile page has personal info, password, 2FA, browser sessions and API tokens', function () {
    $this->actingAs(userWithRole('admin_it'));

    $this->get('/admin/profil')
        ->assertOk()
        ->assertSeeLivewire(PersonalInfo::class)
        ->assertSeeLivewire(UpdatePassword::class)
        ->assertSeeLivewire(TwoFactorAuthentication::class)
        ->assertSeeLivewire(BrowserSessions::class)
        ->assertSeeLivewire(ApiTokens::class);
});

test('the karyawan profile page has no API tokens', function () {
    $this->actingAs(userWithRole('karyawan', 'karyawan'));

    $this->get('/karyawan/profil')
        ->assertOk()
        ->assertSeeLivewire(BrowserSessions::class)
        ->assertDontSeeLivewire(ApiTokens::class);
});

test('a user without email can save their profile', function () {
    Filament::setCurrentPanel('admin');
    $user = userWithRole('admin_it', attributes: ['email' => null]);
    $this->actingAs($user);

    livewire(PersonalInfo::class)
        ->fillForm(['name' => 'Budi Santoso', 'email' => null])
        ->call('submit')
        ->assertHasNoFormErrors();

    expect($user->refresh()->name)->toBe('Budi Santoso')
        ->and($user->email)->toBeNull();
});

test('the profile photo is stored on the private disk', function () {
    Storage::fake('local');
    Storage::fake('public');
    Filament::setCurrentPanel('admin');
    $user = userWithRole('admin_it');
    $this->actingAs($user);

    livewire(PersonalInfo::class)
        ->fillForm(['avatar_url' => UploadedFile::fake()->image('foto.jpg')])
        ->call('submit')
        ->assertHasNoFormErrors();

    $path = $user->refresh()->avatar_url;

    expect($path)->toStartWith('avatars/')
        ->and(Storage::disk('local')->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});

test('the profile photo is shown through a signed temporary link', function () {
    $user = User::factory()->make(['avatar_url' => 'avatars/foto.jpg']);

    expect($user->getFilamentAvatarUrl())->toContain('signature=')
        ->and(User::factory()->make(['avatar_url' => null])->getFilamentAvatarUrl())->toBeNull();
});

test('the email digest starts switched off, and each user picks their own in Profile', function (string $panel, string $role) {
    Filament::setCurrentPanel($panel);
    $user = userWithRole($role, $panel);
    $this->actingAs($user);

    // Default kolom juga mati, supaya user yang sudah ada sebelum fitur ini tidak tiba-tiba menerima ringkasan.
    expect($user->refresh()->digest_frequency)->toBe(DigestFrequency::Mati)
        ->and(collect(Schema::getColumns('users'))->firstWhere('name', 'digest_frequency')['default'])->toContain('mati');

    livewire(PersonalInfo::class)
        ->fillForm(['digest_frequency' => DigestFrequency::Bulanan->value])
        ->call('submit')
        ->assertHasNoFormErrors();

    expect($user->refresh()->digest_frequency)->toBe(DigestFrequency::Bulanan);
})->with([['admin', 'admin_it'], ['karyawan', 'karyawan']]);

test('a user switches the interface language in Profile', function () {
    Filament::setCurrentPanel('admin');
    $user = userWithRole('admin_it');
    $this->actingAs($user);

    expect($user->refresh()->locale)->toBe(Language::Indonesia);

    livewire(PersonalInfo::class)
        ->fillForm(['locale' => Language::English->value])
        ->call('submit')
        ->assertHasNoFormErrors();

    expect($user->refresh()->locale)->toBe(Language::English)
        ->and($user->preferredLocale())->toBe('en');
});
