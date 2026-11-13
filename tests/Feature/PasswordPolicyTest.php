<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Jeffgreco13\FilamentBreezy\Livewire\UpdatePassword;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

test('changing the password on the profile page rejects a weak one', function (string $password) {
    $this->actingAs(userWithRole('admin_it', attributes: ['email' => null, 'password' => 'Kantor2026aman']));

    livewire(UpdatePassword::class)
        ->fillForm(['current_password' => 'Kantor2026aman', 'new_password' => $password, 'new_password_confirmation' => $password])
        ->call('submit')
        ->assertHasFormErrors(['new_password']);
})->with(['12345678', 'passwordtanpaangka']);

test('a user without email can change to a strong password on the profile page', function () {
    $user = userWithRole('admin_it', attributes: ['email' => null, 'password' => 'Kantor2026aman']);
    $this->actingAs($user);

    livewire(UpdatePassword::class)
        ->fillForm(['current_password' => 'Kantor2026aman', 'new_password' => 'Gudang2026baru', 'new_password_confirmation' => 'Gudang2026baru'])
        ->call('submit')
        ->assertHasNoFormErrors();

    expect(Hash::check('Gudang2026baru', $user->refresh()->password))->toBeTrue();
});
