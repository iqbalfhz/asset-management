<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

// Izin khusus pindah aset antar perusahaan; Super Admin otomatis punya, role lain diberi lewat halaman Role.
return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('transfer_assets_between_companies', 'web');
    }
};
