<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hasil per aset dalam satu sesi stock opname.
     */
    public function up(): void
    {
        Schema::create('audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained();
            $table->string('hasil', 16)->index();
            $table->foreignId('ruang_tercatat_id')->nullable()->constrained('rooms');
            $table->foreignId('discan_oleh')->nullable()->constrained('users');
            $table->timestamp('discan_pada')->nullable();
            $table->timestamp('dipindahkan_pada')->nullable();
            $table->timestamps();

            $table->unique(['audit_id', 'asset_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_items');
    }
};
