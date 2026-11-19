<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aksesori yang dipegang karyawan; dikembalikan_pada kosong berarti masih dipegang.
     */
    public function up(): void
    {
        Schema::create('accessory_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accessory_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->unsignedInteger('jumlah');
            $table->dateTime('diserahkan_pada');
            $table->foreignId('diserahkan_oleh')->nullable()->constrained('users');
            $table->dateTime('dikembalikan_pada')->nullable()->index();
            $table->foreignId('dikembalikan_oleh')->nullable()->constrained('users');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accessory_checkouts');
    }
};
