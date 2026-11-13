<?php

namespace App\Http\Responses;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

// Setelah masuk: kembali ke halaman yang tadinya dibuka (misalnya hasil scan QR), selain itu ke panel sesuai role.
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $intended = session()->pull('url.intended');

        if (is_string($intended) && $this->canOpen($user, $intended)) {
            return redirect()->to($intended);
        }

        return redirect()->to($user->homePanel()->getUrl());
    }

    // Tautan ke panel yang tidak boleh dibuka (karyawan membuka tautan admin) diganti panel sesuai role, bukan halaman 403.
    private function canOpen(User $user, string $url): bool
    {
        $path = '/'.trim((string) parse_url($url, PHP_URL_PATH), '/');

        foreach (Filament::getPanels() as $panel) {
            $prefix = '/'.$panel->getPath();

            if ($panel->getPath() !== '' && ($path === $prefix || str_starts_with($path, $prefix.'/'))) {
                return $user->canAccessPanel($panel);
            }
        }

        return true;
    }
}
