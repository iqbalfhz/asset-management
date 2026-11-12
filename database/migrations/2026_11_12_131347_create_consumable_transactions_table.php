<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat stok masuk dan keluar; stok_sesudah dipakai untuk mencocokkan riwayat dengan stok.
     */
    public function up(): void
    {
        Schema::create('consumable_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumable_id')->constrained();
            $table->string('jenis', 8)->index();
            $table->unsignedInteger('jumlah');
            $table->unsignedInteger('stok_sesudah');
            $table->foreignId('employee_id')->nullable()->constrained();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->date('tanggal');
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumable_transactions');
    }
};
