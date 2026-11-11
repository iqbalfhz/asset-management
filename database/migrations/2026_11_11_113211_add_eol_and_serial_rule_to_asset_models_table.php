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
        Schema::table('asset_models', function (Blueprint $table) {
            $table->unsignedSmallInteger('eol_bulan')->nullable()->after('depreciation_id');
            $table->boolean('wajib_nomor_seri')->default(false)->after('eol_bulan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_models', function (Blueprint $table) {
            $table->dropColumn(['eol_bulan', 'wajib_nomor_seri']);
        });
    }
};
