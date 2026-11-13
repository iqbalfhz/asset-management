<?php

use App\Enums\LabelField;
use App\Enums\LabelSize;
use App\Filament\Karyawan\Pages\MyAssetDetail;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Status;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    $this->withoutVite();

    $this->tcm = Company::factory()->create(['nama' => 'Tangcity', 'kode' => 'TCM']);
    $this->itCategory = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->laptop = Asset::factory()->create([
        'company_id' => $this->tcm->id,
        'category_id' => $this->itCategory->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
        'nama' => 'Laptop Keuangan',
        'no_seri' => 'SN-777',
    ]);
});

test('a guest scanning a label is sent to sign in and comes back to the label afterwards', function () {
    $this->get("/a/{$this->laptop->kode}")
        ->assertRedirect('/login')
        ->assertSessionHas('url.intended', url("/a/{$this->laptop->kode}"));
});

test('an admin scanning a label lands on the asset page', function () {
    $this->actingAs($this->adminIt);

    $this->get("/a/{$this->laptop->kode}")->assertRedirect(route('filament.admin.assets.scan', $this->laptop->kode));
    $this->get(route('filament.admin.assets.scan', $this->laptop->kode))
        ->assertRedirect(AssetResource::getUrl('view', ['record' => $this->laptop], panel: 'admin'));
    $this->get(AssetResource::getUrl('view', ['record' => $this->laptop], panel: 'admin'))
        ->assertOk()
        ->assertSee('Laptop Keuangan');
});

test('an admin of another category gets 404 when scanning the asset', function () {
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));

    $this->get(route('filament.admin.assets.scan', $this->laptop->kode))->assertNotFound();
});

test('an employee scanning a label sees the asset only if they hold it', function () {
    $holder = userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id]);
    $employee = Employee::factory()->create(['company_id' => $this->tcm->id, 'user_id' => $holder->id]);
    $this->actingAs($this->adminIt);
    Assignment::handOver(new Collection([$this->laptop]), $employee, ['tanggal' => now()->toDateString(), 'kondisi' => 'Baik']);
    $detailUrl = MyAssetDetail::getUrl(['kode' => $this->laptop->kode], panel: 'karyawan');

    $this->actingAs($holder);
    $this->get("/a/{$this->laptop->kode}")->assertRedirect($detailUrl);
    $this->get($detailUrl)->assertOk()->assertSee('Laptop Keuangan');

    $other = userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id]);
    Employee::factory()->create(['company_id' => $this->tcm->id, 'user_id' => $other->id]);
    $this->actingAs($other)->get($detailUrl)->assertNotFound();
});

test('the label page prints only visible assets with a QR code and a barcode of the asset code', function () {
    $hidden = Asset::factory()->create([
        'company_id' => $this->tcm->id,
        'category_id' => Category::factory()->create(['role_id' => Role::findByName('admin_ga')->id])->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
    ]);
    $this->actingAs($this->adminIt);

    $this->get(route('filament.admin.labels', ['aset' => "{$this->laptop->id},{$hidden->id}"]))
        ->assertOk()
        ->assertSee('size: 50mm 25mm', false)
        ->assertSee($this->laptop->kode)
        ->assertSee('Tangcity')
        ->assertSee('<div class="qr"><svg', false)
        ->assertSee("<desc>{$this->laptop->kode}</desc>", false)
        ->assertDontSee($hidden->kode);
});

test('the label size and content follow the company settings', function () {
    Setting::put('label_ukuran', LabelSize::A4Sheet3x8->value, $this->tcm->id);
    Setting::put('label_isi', [LabelField::Qr->value, LabelField::NomorSeri->value], $this->tcm->id);
    $this->actingAs($this->adminIt);

    $this->get(route('filament.admin.labels', ['aset' => $this->laptop->id]))
        ->assertOk()
        ->assertSee('size: 210mm 297mm', false)
        ->assertSee('SN SN-777')
        ->assertDontSee('<div class="barcode">', false)
        ->assertDontSee('Tangcity');
});

test('the QR code on a label encodes the short scan address of the asset', function () {
    $expected = (new Writer(new ImageRenderer(new RendererStyle(256, 1), new SvgImageBackEnd)))
        ->writeString(url("/a/{$this->laptop->kode}"), ecLevel: ErrorCorrectionLevel::M());
    $this->actingAs($this->adminIt);

    $this->get(route('filament.admin.labels', ['aset' => $this->laptop->id]))
        ->assertOk()
        ->assertSee(preg_replace('/^<\?xml[^>]*>\s*/', '', $expected), false);
});

test('a super admin saves the label size and content per company', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id]));

    livewire(Settings::class)
        ->fillForm(['label_ukuran' => LabelSize::Thermal40x30->value, 'label_isi' => [LabelField::Qr->value]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('label_ukuran', null, $this->tcm->id))->toBe(LabelSize::Thermal40x30->value)
        ->and(Setting::get('label_isi', null, $this->tcm->id))->toBe([LabelField::Qr->value]);
});
