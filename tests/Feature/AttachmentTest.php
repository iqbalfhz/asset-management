<?php

use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\RelationManagers\AttachmentsRelationManager;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Storage::fake('local');
    Filament::setCurrentPanel('admin');

    $this->company = Company::factory()->create();
    $laptop = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->asset = Asset::factory()->create(['company_id' => $this->company->id, 'category_id' => $laptop->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->company->id]);
});

function storedAttachment(Asset $asset): Attachment
{
    $attachment = Attachment::factory()->for($asset, 'attachable')->create(['nama_file' => 'faktur.pdf']);
    Storage::disk('local')->put($attachment->path, 'isi faktur');

    return $attachment;
}

test('an uploaded attachment is stored privately with its name, size and uploader', function () {
    $this->actingAs($this->adminIt);

    livewire(AttachmentsRelationManager::class, ['ownerRecord' => $this->asset, 'pageClass' => EditAsset::class])
        ->callAction(TestAction::make('create')->table(), data: ['path' => UploadedFile::fake()->createWithContent('faktur.pdf', str_repeat('%PDF-1.4 ', 200))])
        ->assertHasNoActionErrors();

    $attachment = $this->asset->attachments()->sole();

    expect($attachment->nama_file)->toBe('faktur.pdf')
        ->and($attachment->ukuran)->toBeGreaterThan(0)
        ->and($attachment->diunggah_oleh)->toBe($this->adminIt->id)
        ->and($attachment->path)->toStartWith('attachments/')
        ->and(Storage::disk('local')->exists($attachment->path))->toBeTrue();
});

test('an admin who may see the asset can download its attachment', function () {
    $attachment = storedAttachment($this->asset);
    $this->actingAs($this->adminIt);

    $this->get(route('filament.admin.attachments.download', $attachment))
        ->assertOk()
        ->assertDownload('faktur.pdf');
});

test('an admin of another category cannot download it', function () {
    $attachment = storedAttachment($this->asset);
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->company->id]));

    $this->get(route('filament.admin.attachments.download', $attachment))->assertNotFound();
});

test('a guest is sent to the login page', function () {
    $attachment = storedAttachment($this->asset);

    $this->get(route('filament.admin.attachments.download', $attachment))->assertRedirect('/admin/login');
});

test('deleting an attachment also removes its file', function () {
    $attachment = storedAttachment($this->asset);
    $this->actingAs($this->adminIt);

    livewire(AttachmentsRelationManager::class, ['ownerRecord' => $this->asset, 'pageClass' => EditAsset::class])
        ->callAction(TestAction::make('delete')->table($attachment));

    expect(Attachment::find($attachment->id))->toBeNull()
        ->and(Storage::disk('local')->exists($attachment->path))->toBeFalse();
});
