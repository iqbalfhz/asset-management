<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Ringkasan email pilihan user di Profil; awalnya mati untuk semua (ringkasan harian dianggap terlalu sering).
            $table->string('digest_frequency', 10)->default('mati')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('digest_frequency');
        });
    }
};
