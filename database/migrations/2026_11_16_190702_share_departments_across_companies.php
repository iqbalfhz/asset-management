<?php

use App\Models\Department;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Departemen dipakai bersama semua perusahaan (contoh: Collection berisi karyawan EFM, PAKAR, dan SSK).
     * Departemen bernama sama digabung ke yang paling awal dibuat; karyawan, aset, dan riwayat serah-terimanya ikut pindah.
     * Penggabungan tidak bisa dibatalkan, jadi tidak ada down().
     */
    public function up(): void
    {
        $morphClass = (new Department)->getMorphClass();

        $groups = DB::table('departments')
            ->orderBy('id')
            ->get(['id', 'nama', 'manager_id'])
            ->groupBy(fn (object $department): string => mb_strtolower(trim($department->nama)));

        foreach ($groups as $departments) {
            $kept = $departments->first();
            $duplicateIds = $departments->skip(1)->pluck('id')->all();

            if ($duplicateIds === []) {
                continue;
            }

            DB::table('employees')->whereIn('department_id', $duplicateIds)->update(['department_id' => $kept->id]);
            DB::table('assets')->where('assigned_type', $morphClass)->whereIn('assigned_id', $duplicateIds)->update(['assigned_id' => $kept->id]);
            DB::table('assignments')->where('target_type', $morphClass)->whereIn('target_id', $duplicateIds)->update(['target_id' => $kept->id]);

            if ($kept->manager_id === null) {
                DB::table('departments')->where('id', $kept->id)->update(['manager_id' => $departments->pluck('manager_id')->filter()->first()]);
            }

            DB::table('departments')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropUnique(['company_id', 'nama']);
            $table->dropColumn('company_id');
            $table->unique('nama');
        });
    }
};
