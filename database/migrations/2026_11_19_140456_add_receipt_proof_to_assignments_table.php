<?php

use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('receipt_method')->nullable()->after('signature_path');
            $table->string('receipt_path')->nullable()->after('receipt_method');
            $table->foreignId('confirmed_by')->nullable()->after('receipt_path')->constrained('users')->nullOnDelete();
        });

        // Sebelum ini penerimaan oleh karyawan hanya bisa lewat tombol Terima di aplikasinya.
        DB::table('assignments')
            ->where('jenis', 'keluar')
            ->where('status', 'diterima')
            ->where('target_type', Employee::class)
            ->update(['receipt_method' => 'aplikasi']);
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['receipt_method', 'receipt_path']);
        });
    }
};
