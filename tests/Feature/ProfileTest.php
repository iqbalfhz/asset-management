<?php

use App\Livewire\PersonalInfo;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Jeffgreco13\FilamentBreezy\Livewire\BrowserSessions;
use Jeffgreco13\FilamentBreezy\Livewire\SanctumTokens;
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
        ->assertSeeLivewire(SanctumTokens::class);
});

test('the karyawan profile page has no API tokens', function () {
    $this->actingAs(userWithRole('karyawan', 'karyawan'));

    $this->get('/karyawan/profil')
        ->assertOk()
        ->assertSeeLivewire(BrowserSessions::class)
        ->assertDontSeeLivewire(SanctumTokens::class);
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
