<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Komponen (RAM, SSD) yang dipasang ke aset per jumlah lalu bisa dilepas.
     */
    public function up(): void
    {
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('role_id')->constrained();
            $table->string('nama');
            $table->foreignId('manufacturer_id')->nullable()->constrained();
            $table->unsignedInteger('jumlah')->default(0);
            $table->unsignedInteger('stok_minimum')->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('components');
    }
};
