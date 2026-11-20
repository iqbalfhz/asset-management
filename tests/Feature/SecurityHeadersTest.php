<?php

use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Maintenance;
use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('every page carries the basic security headers', function () {
    $this->get('/login')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'");
});

test('HSTS is sent over HTTPS, and always in production where the tunnel reaches the app over plain HTTP', function () {
    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');

    $this->app['env'] = 'production';

    $this->get('http://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

test('in production only the app domain and localhost are served, so links cannot point to a forged host', function () {
    $this->app['env'] = 'production';
    config(['app.url' => 'https://aset.contoh.test']);

    $this->get('https://aset.contoh.test/login')->assertOk();
    $this->get('http://localhost/up')->assertOk();
    $this->get('https://palsu.example/login')->assertBadRequest();

    Request::setTrustedHosts([]);
});

test('a damage photo is served in a sandbox so a planted script cannot run', function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Storage::fake('local');
    $adminIt = userWithRole('admin_it');
    $asset = Asset::factory()->create(['category_id' => Category::factory()->create(['role_id' => Role::findByName('admin_it')->id])->id]);
    Storage::disk('local')->put('maintenances/foto.jpg', UploadedFile::fake()->image('foto.jpg')->getContent());
    $report = Maintenance::factory()->create(['asset_id' => $asset->id, 'status' => MaintenanceStatus::Dilaporkan, 'foto' => 'maintenances/foto.jpg']);

    $this->actingAs($adminIt)
        ->get(route('filament.admin.maintenances.photo', $report))
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
});
