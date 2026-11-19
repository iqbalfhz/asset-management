<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Komponen yang terpasang di aset; dilepas_pada kosong berarti masih terpasang.
     */
    public function up(): void
    {
        Schema::create('component_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained();
            $table->foreignId('asset_id')->constrained();
            $table->unsignedInteger('jumlah');
            $table->dateTime('dipasang_pada');
            $table->foreignId('dipasang_oleh')->nullable()->constrained('users');
            $table->dateTime('dilepas_pada')->nullable()->index();
            $table->foreignId('dilepas_oleh')->nullable()->constrained('users');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_installations');
    }
};
