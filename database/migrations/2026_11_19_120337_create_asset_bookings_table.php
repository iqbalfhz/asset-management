<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jadwal pemakaian barang bersama; status aset tidak berubah dan tidak ada BAST.
        Schema::create('asset_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->dateTime('mulai');
            $table->dateTime('selesai');
            $table->text('keperluan');
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('alasan_batal')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'mulai', 'selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_bookings');
    }
};
