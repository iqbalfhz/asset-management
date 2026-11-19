<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paket barang (contoh: laptop + mouse + headset + lisensi) yang diserahkan dalam satu langkah.
     */
    public function up(): void
    {
        Schema::create('kits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('nama');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kits');
    }
};
