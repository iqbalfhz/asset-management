<?php

use App\Filament\Imports\EmployeeImporter;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));
});

test('a new employee joins the company of the importing account with its department', function () {
    $finance = Department::factory()->create(['nama' => 'Keuangan']);

    $employee = EmployeeImporter::test()->import(['nik' => '2026001', 'nama' => 'Rina Wulandari', 'jabatan' => 'Staf Keuangan', 'department' => 'Keuangan'])
        ->assertImported()
        ->getRecord();

    expect($employee->company_id)->toBe($this->tcm->id)
        ->and($employee->department_id)->toBe($finance->id)
        ->and($employee->jabatan)->toBe('Staf Keuangan');
});

test('an existing NIK fails the row unless updating is chosen', function () {
    $employee = Employee::factory()->create(['company_id' => $this->tcm->id, 'nik' => '2026001', 'nama' => 'Rina', 'jabatan' => 'Staf']);
    $row = ['nik' => '2026001', 'nama' => 'Rina Wulandari', 'jabatan' => ''];

    EmployeeImporter::test()->import($row)->assertHasRowFailure('NIK 2026001 sudah terdaftar.');
    expect($employee->refresh()->nama)->toBe('Rina');

    EmployeeImporter::test(options: ['updateExisting' => true])->import($row)->assertImported();
    expect($employee->refresh()->nama)->toBe('Rina Wulandari')
        ->and($employee->jabatan)->toBe('Staf')
        ->and(Employee::count())->toBe(1);
});

test('rows for an inactive company are not imported', function () {
    Company::factory()->create(['kode' => 'OLD', 'is_active' => false]);

    EmployeeImporter::test()->import(['company' => 'OLD', 'nik' => '2026003', 'nama' => 'Budi'])
        ->assertHasErrors(['company']);

    $this->tcm->update(['is_active' => false]);

    EmployeeImporter::test()->import(['nik' => '2026004', 'nama' => 'Ani'])
        ->assertHasRowFailure('Isi kolom perusahaan.');

    expect(Employee::count())->toBe(0);
});

test('a department that does not exist fails the row', function () {
    Department::factory()->create(['nama' => 'Keuangan']);

    EmployeeImporter::test()->import(['nik' => '2026002', 'nama' => 'Budi', 'department' => 'Gudang'])
        ->assertHasErrors(['department']);

    expect(Employee::count())->toBe(0);
});
