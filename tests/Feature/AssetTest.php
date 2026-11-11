<?php

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Depreciation;
use App\Models\Status;
use Database\Seeders\StatusSeeder;

beforeEach(function () {
    $this->seed(StatusSeeder::class);
    $this->travelTo(now()->setDate(2026, 11, 11));
    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->laptop = Category::factory()->create(['kode' => 'LPT']);
});

test('asset codes follow company, category, year and run on per combination', function () {
    $first = Asset::factory()->create(['company_id' => $this->tcm->id, 'category_id' => $this->laptop->id]);
    $second = Asset::factory()->create(['company_id' => $this->tcm->id, 'category_id' => $this->laptop->id]);
    $printer = Asset::factory()->create(['company_id' => $this->tcm->id, 'category_id' => Category::factory()->create(['kode' => 'PRN'])->id]);

    $this->travelTo(now()->setDate(2027, 1, 2));
    $nextYear = Asset::factory()->create(['company_id' => $this->tcm->id, 'category_id' => $this->laptop->id]);

    expect($first->kode)->toBe('TCM-LPT-2026-00001')
        ->and($second->kode)->toBe('TCM-LPT-2026-00002')
        ->and($printer->kode)->toBe('TCM-PRN-2026-00001')
        ->and($nextYear->kode)->toBe('TCM-LPT-2027-00001');
});

test('an asset code cannot be changed', function () {
    $asset = Asset::factory()->create(['company_id' => $this->tcm->id, 'category_id' => $this->laptop->id]);

    expect(fn () => $asset->forceFill(['kode' => 'TCM-LPT-2026-99999'])->save())->toThrow(LogicException::class)
        ->and($asset->refresh()->kode)->toBe('TCM-LPT-2026-00001');
});

test('a new asset starts as Disiapkan or Tersedia only', function () {
    $asset = Asset::factory()->create(['status_id' => Status::named(Status::DISIAPKAN)->id]);

    expect($asset->status->nama)->toBe(Status::DISIAPKAN)
        ->and(fn () => Asset::factory()->create(['status_id' => Status::named('Dipakai')->id]))->toThrow(LogicException::class);
});

test('status cannot be edited directly, only moved between Disiapkan and Tersedia', function () {
    $asset = Asset::factory()->create(['status_id' => Status::named(Status::DISIAPKAN)->id]);

    expect(fn () => $asset->forceFill(['status_id' => Status::named('Rusak')->id])->save())->toThrow(LogicException::class);

    $asset->refresh()->markAs(Status::named(Status::TERSEDIA));
    expect($asset->refresh()->status->nama)->toBe(Status::TERSEDIA);

    expect(fn () => $asset->markAs(Status::named('Hilang')))->toThrow(LogicException::class)
        ->and($asset->refresh()->status->nama)->toBe(Status::TERSEDIA);
});

test('an asset cannot be deleted', function () {
    $asset = Asset::factory()->create();

    expect(fn () => $asset->delete())->toThrow(LogicException::class)
        ->and(Asset::find($asset->id))->not->toBeNull();
});

test('EOL date and depreciation class come from the asset model when left empty', function () {
    $empatTahun = Depreciation::factory()->create(['jumlah_bulan' => 48]);
    $model = AssetModel::factory()->create(['eol_bulan' => 60, 'depreciation_id' => $empatTahun->id]);

    $asset = Asset::factory()->create(['asset_model_id' => $model->id, 'tgl_beli' => '2026-01-15']);
    $manualEol = Asset::factory()->create(['asset_model_id' => $model->id, 'tgl_beli' => '2026-01-15', 'eol_date' => '2028-01-01']);

    expect($asset->eol_date->toDateString())->toBe('2031-01-15')
        ->and($asset->depreciation_id)->toBe($empatTahun->id)
        ->and($manualEol->eol_date->toDateString())->toBe('2028-01-01');
});
