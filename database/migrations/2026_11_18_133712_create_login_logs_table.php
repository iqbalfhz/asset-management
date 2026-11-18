<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log masuk: login berhasil, gagal, terkunci, dan keluar, dengan IP dan lokasinya dari Cloudflare.
     */
    public function up(): void
    {
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->string('hasil', 10)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('login')->nullable();
            $table->string('ip_address', 45)->index();
            $table->string('negara', 2)->nullable();
            $table->string('kota', 100)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
