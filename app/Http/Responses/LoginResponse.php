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

        if (is_string($intended) && $this->shouldReturnTo($user, $intended)) {
            return redirect()->to($intended);
        }

        return redirect()->to($user->homePanel()->getUrl());
    }

    // Tujuan di dalam panel dipakai hanya kalau itu panel utama role-nya (admin selalu mulai di /admin); di luar panel (scan QR) selalu dipakai.
    private function shouldReturnTo(User $user, string $url): bool
    {
        $path = '/'.trim((string) parse_url($url, PHP_URL_PATH), '/');

        foreach (Filament::getPanels() as $panel) {
            $prefix = '/'.$panel->getPath();

            if ($panel->getPath() !== '' && ($path === $prefix || str_starts_with($path, $prefix.'/'))) {
                return $panel->getId() === $user->homePanel()->getId();
            }
        }

        return true;
    }
}
