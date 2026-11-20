<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Error aplikasi untuk widget Kesehatan sistem: satu baris per jenis dan lokasi, dengan jumlah kejadiannya.
     */
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->char('sidik', 40)->unique();
            $table->string('jenis');
            $table->text('pesan');
            $table->string('lokasi');
            $table->string('url')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('jumlah')->default(1);
            $table->timestamps();
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
