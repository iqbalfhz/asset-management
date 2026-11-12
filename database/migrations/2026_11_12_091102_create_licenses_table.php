<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lisensi software dengan jumlah seat; product key terenkripsi.
     */
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('role_id')->constrained();
            $table->string('nama_software');
            $table->text('product_key')->nullable();
            $table->unsignedInteger('jumlah_seat');
            $table->date('tgl_habis')->nullable()->index();
            $table->unsignedBigInteger('harga')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
