<?php

use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Testing\TestView;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    $this->travelTo(now()->setDate(2026, 11, 11)->setTime(10, 0));

    $this->tcm = Company::factory()->create(['nama' => 'Tangcity', 'kode' => 'TCM']);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id, 'name' => 'Iqbal']);
    $this->employee = Employee::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Rina Wulandari', 'nik' => '2026001']);
    $this->asset = Asset::factory()->create([
        'company_id' => $this->tcm->id,
        'category_id' => Category::factory()->create(['role_id' => Role::findByName('admin_it')->id])->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
        'nama' => 'Laptop Keuangan',
    ]);

    $this->actingAs($this->adminIt);
    Assignment::handOver(new Collection([$this->asset]), $this->employee, ['tanggal' => '2026-11-11', 'kondisi' => 'Baik']);
    $this->assignment = $this->asset->assignments()->sole();
});

function renderBast(Assignment $assignment, ?string $kop = null): TestView
{
    return test()->view('pdf.bast', [
        'first' => $assignment,
        'rows' => Assignment::where('no_bast', $assignment->no_bast)->get(),
        'company' => $assignment->asset->company,
        'kop' => $kop,
        'logo' => null,
    ]);
}

test('an admin who may see the asset downloads its BAST as a PDF', function () {
    $this->get(route('filament.admin.bast.download', $this->assignment))
        ->assertOk()
        ->assertDownload('BAST-TCM-2026-0001.pdf')
        ->assertHeader('content-type', 'application/pdf');
});

test('an admin of another category gets 404 for the BAST', function () {
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));

    $this->get(route('filament.admin.bast.download', $this->assignment))->assertNotFound();
});

test('a guest is sent to the login page instead of the BAST', function () {
    auth()->logout();

    $this->get(route('filament.admin.bast.download', $this->assignment))->assertRedirect('/admin/login');
});

test('a hand over BAST names both parties, lists the assets and says it awaits acceptance', function () {
    renderBast($this->assignment)
        ->assertSee('BERITA ACARA SERAH TERIMA ASET')
        ->assertSee('BAST/TCM/2026/0001')
        ->assertSee('Pada tanggal 11 November 2026')
        ->assertSee('Iqbal, atas nama Tangcity')
        ->assertSee('Rina Wulandari (2026001)')
        ->assertSee($this->asset->kode)
        ->assertSee('Laptop Keuangan')
        ->assertSee('Menunggu konfirmasi penerimaan dari Pihak Kedua di aplikasi.');
});

test('an accepted BAST shows when the employee accepted it', function () {
    $this->assignment->update(['accepted_at' => now()]);

    renderBast($this->assignment)->assertSee('Diterima oleh Pihak Kedua melalui aplikasi pada 11 November 2026, 10:00.');
});

test('a return BAST has the employee as the one returning', function () {
    $noBast = Assignment::takeBack(new Collection([$this->asset->refresh()]), ['tanggal' => '2026-11-11', 'kondisi' => 'Baik']);

    renderBast(Assignment::where('no_bast', $noBast)->sole())
        ->assertSee('BERITA ACARA PENGEMBALIAN ASET')
        ->assertSeeInOrder(['Pihak Pertama (yang mengembalikan)', 'Rina Wulandari (2026001)', 'Pihak Kedua (yang menerima)', 'Iqbal, atas nama Tangcity']);
});

test('the letterhead text is printed as text, not as HTML', function () {
    renderBast($this->assignment, '<b>PT Tangcity</b><img src="http://contoh.test/x.png">')
        ->assertDontSee('<img src="http://contoh.test/x.png">', false)
        ->assertSee('&lt;b&gt;PT Tangcity&lt;/b&gt;', false);
});
