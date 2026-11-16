<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use App\Models\Concerns\ReceivesAssets;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Dipakai bersama semua perusahaan: satu departemen bisa berisi karyawan dari beberapa perusahaan.
#[Fillable(['nama', 'manager_id'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory, LogsChanges, ReceivesAssets;

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }
}
