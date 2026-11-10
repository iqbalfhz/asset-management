<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_id')->constrained();
            $table->foreignId('category_id')->constrained();
            $table->foreignId('depreciation_id')->nullable()->constrained();
            $table->string('nama');
            $table->string('nomor_model')->nullable();
            $table->string('foto')->nullable();
            $table->unique(['manufacturer_id', 'nama']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_models');
    }
};
