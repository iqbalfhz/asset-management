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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->foreignId('category_id')->constrained();
            $table->foreignId('asset_model_id')->nullable()->constrained();
            $table->foreignId('status_id')->constrained();
            $table->string('no_seri')->nullable()->index();
            $table->string('foto')->nullable();
            $table->foreignId('room_id')->nullable()->constrained();
            $table->foreignId('default_room_id')->nullable()->constrained('rooms');
            $table->nullableMorphs('assigned');
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->date('tgl_beli')->nullable();
            $table->unsignedBigInteger('harga_beli')->nullable();
            $table->foreignId('depreciation_id')->nullable()->constrained();
            $table->string('order_number')->nullable()->index();
            $table->date('garansi_sampai')->nullable();
            $table->date('eol_date')->nullable();
            $table->json('spesifikasi')->nullable();
            $table->boolean('is_byod')->default(false);
            $table->boolean('requestable')->default(false);
            $table->date('next_audit_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
