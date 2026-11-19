<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permintaan aset dari karyawan, diputuskan admin pengelola kategorinya.
     */
    public function up(): void
    {
        Schema::create('asset_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('category_id')->constrained();
            $table->text('alasan');
            $table->string('status', 10)->index();
            $table->text('catatan')->nullable();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users');
            $table->foreignId('assignment_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_requests');
    }
};
