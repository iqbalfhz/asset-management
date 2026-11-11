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
    $finance = Department::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Keuangan']);

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

test('a department of another company is not used', function () {
    Department::factory()->create(['company_id' => Company::factory()->create()->id, 'nama' => 'Gudang']);

    EmployeeImporter::test()->import(['nik' => '2026002', 'nama' => 'Budi', 'department' => 'Gudang'])
        ->assertHasErrors(['department']);

    expect(Employee::count())->toBe(0);
});
