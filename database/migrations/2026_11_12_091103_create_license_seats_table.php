<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pemakai seat lisensi: karyawan atau aset. Seat aktif = belum dilepas.
     */
    public function up(): void
    {
        Schema::create('license_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained();
            $table->foreignId('employee_id')->nullable()->constrained();
            $table->foreignId('asset_id')->nullable()->constrained();
            $table->timestamp('dipasang_pada');
            $table->foreignId('dipasang_oleh')->nullable()->constrained('users');
            $table->timestamp('dilepas_pada')->nullable();
            $table->foreignId('dilepas_oleh')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['license_id', 'dilepas_pada']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_seats');
    }
};
