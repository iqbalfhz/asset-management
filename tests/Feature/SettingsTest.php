<?php

use App\Filament\Pages\Settings;
use App\Models\Company;
use App\Models\Department;
use App\Models\Setting;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->companyA = Company::factory()->create();
    $this->companyB = Company::factory()->create();
    $this->superAdmin = userWithRole('super_admin', attributes: ['company_id' => $this->companyA->id]);
});

test('admin IT cannot open the settings page', function () {
    $this->actingAs(userWithRole('admin_it'))->get(Settings::getUrl())->assertForbidden();
});

test('a missing setting falls back to its default', function () {
    expect(Setting::get('company_separation', false))->toBeFalse()
        ->and(Setting::get('format_nomor_bast', 'BAST/{NOMOR}', $this->companyA->id))->toBe('BAST/{NOMOR}');
});

test('super admin saves global and per-company settings without mixing companies', function () {
    $this->actingAs($this->superAdmin);

    livewire(Settings::class)
        ->fillForm([
            'company_separation' => true,
            'telegram_enabled' => false,
            'company_id' => $this->companyA->id,
            'kop_bast' => 'PT Tangcity',
            'format_nomor_bast' => 'BAST/{KODE}/{TAHUN}/{NOMOR}',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    livewire(Settings::class)
        ->fillForm(['company_id' => $this->companyB->id])
        ->fillForm(['kop_bast' => 'PT Lain', 'format_nomor_bast' => 'BA/{NOMOR}'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('company_separation'))->toBeTrue()
        ->and(Setting::get('telegram_enabled'))->toBeFalse()
        ->and(Setting::get('kop_bast', null, $this->companyA->id))->toBe('PT Tangcity')
        ->and(Setting::get('kop_bast', null, $this->companyB->id))->toBe('PT Lain')
        ->and(Setting::get('format_nomor_bast', null, $this->companyB->id))->toBe('BA/{NOMOR}');
});

test('turning separation on in the settings page separates the data', function () {
    Department::factory()->create(['company_id' => $this->companyA->id]);
    Department::factory()->create(['company_id' => $this->companyB->id]);

    $this->actingAs($this->superAdmin);

    livewire(Settings::class)
        ->fillForm(['company_separation' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->companyA->id]));

    expect(Department::pluck('company_id')->all())->toBe([$this->companyA->id]);
});

test('a BAST number format without {NOMOR} is refused', function () {
    $this->actingAs($this->superAdmin);

    livewire(Settings::class)
        ->fillForm(['company_id' => $this->companyA->id])
        ->fillForm(['format_nomor_bast' => 'BAST/{KODE}/{TAHUN}'])
        ->call('save')
        ->assertHasFormErrors(['format_nomor_bast' => 'regex']);

    expect(Setting::get('format_nomor_bast', null, $this->companyA->id))->toBeNull();
});
