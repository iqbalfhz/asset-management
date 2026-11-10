<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = config('app.seed_password');

        if (Validator::make(['password' => $password], ['password' => ['required', Password::defaults()]])->fails()) {
            throw new RuntimeException('SEED_PASSWORD di .env kosong atau tidak memenuhi kebijakan password (minimal 12 karakter, huruf dan angka).');
        }

        $company = Company::where('kode', 'TCM')->firstOrFail();

        $accounts = [
            'superadmin' => ['Super Admin', 'super_admin'],
            'adminit' => ['Admin IT', 'admin_it'],
            'adminga' => ['Admin GA', 'admin_ga'],
            'karyawan' => ['Karyawan', 'karyawan'],
        ];

        foreach ($accounts as $username => [$name, $role]) {
            $user = User::firstWhere('username', $username) ?? new User;

            $user->forceFill([
                'company_id' => $company->id,
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'is_active' => true,
            ])->save();

            $user->syncRoles([$role]);
        }
    }
}
