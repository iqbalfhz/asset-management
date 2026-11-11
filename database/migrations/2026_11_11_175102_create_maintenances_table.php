<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laporan kerusakan dari karyawan dan perbaikan oleh admin.
     */
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained();
            $table->foreignId('dilapor_oleh')->nullable()->constrained('employees');
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users');
            $table->string('status', 12)->index();
            $table->text('keluhan');
            $table->string('foto')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->unsignedBigInteger('biaya')->nullable();
            $table->date('tgl_mulai')->nullable();
            $table->date('tgl_selesai')->nullable();
            $table->text('hasil')->nullable();
            $table->foreignId('status_sebelum_id')->nullable()->constrained('statuses');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
