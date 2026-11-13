<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AuthPanelProvider;
use App\Providers\Filament\KaryawanPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    KaryawanPanelProvider::class,
    AuthPanelProvider::class,
];
