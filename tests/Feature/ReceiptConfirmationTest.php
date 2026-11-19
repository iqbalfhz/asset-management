<?php

use App\Enums\AssignmentStatus;
use App\Enums\ReceiptMethod;
use App\Filament\Forms\Components\SignaturePad;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Filament\Resources\Assignments\Pages\ListAssignments;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Room;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    $this->travelTo('2026-11-19 10:00');
    Storage::fake(Assignment::PROOF_DISK);
    Filament::setCurrentPanel('admin');

    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $laptops = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->laptop = availableAsset($this->tcm, $laptops);
    $this->charger = availableAsset($this->tcm, $laptops);
    // Tanpa akun login, jadi tidak bisa menekan Terima sendiri.
    $this->rina = Employee::factory()->create(['company_id' => $this->tcm->id]);
    $this->actingAs($this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id, 'name' => 'Iqbal']));
});

// Serah-terima dua aset yang belum ditandatangani; baris pertama BAST-nya.
function waitingHandOver(): Assignment
{
    $noBast = Assignment::handOver(new Collection([test()->laptop, test()->charger]), test()->rina, ['tanggal' => '2026-11-19', 'kondisi' => 'Baik']);

    return Assignment::query()->where('no_bast', $noBast)->orderBy('id')->first();
}

test('an employee who signs on the admin\'s screen at hand-over has received the asset straight away', function () {
    livewire(ListAssets::class)
        ->callAction(TestAction::make('handOver')->table($this->laptop), data: [
            'target_type' => Employee::class,
            'target_id' => $this->rina->id,
            'tanggal' => '2026-11-19',
            'kondisi' => 'Baik',
            'tanda_tangan' => signatureDataUrl(),
        ])
        ->assertNotified('Serah-terima tersimpan');

    $assignment = Assignment::query()->sole();

    expect($assignment->status)->toBe(AssignmentStatus::Diterima)
        ->and($assignment->receipt_method)->toBe(ReceiptMethod::Layar)
        ->and($assignment->confirmedBy->is($this->adminIt))->toBeTrue()
        ->and(Storage::disk(Assignment::PROOF_DISK)->get($assignment->signature_path))->toStartWith("\x89PNG");

    renderBast($assignment)
        ->assertSee('Ditandatangani Pihak Kedua di hadapan Iqbal pada 19 November 2026, 10:00.')
        ->assertSee('<img src="data:image/png;base64,', false);
});

test('a hand-over to a room asks for no signature and counts as received', function () {
    livewire(ListAssets::class)
        ->callAction(TestAction::make('handOver')->table($this->laptop), data: [
            'target_type' => Room::class,
            'target_id' => roomOf($this->tcm)->id,
            'tanggal' => '2026-11-19',
            'kondisi' => 'Baik',
        ])
        ->assertNotified('Serah-terima tersimpan');

    expect(Assignment::query()->sole())
        ->status->toBe(AssignmentStatus::Diterima)
        ->receipt_method->toBeNull();
});

test('an admin confirms a waiting hand-over with a photo of the signed BAST, which then prints as its attachment', function () {
    $assignment = waitingHandOver();

    livewire(ListAssignments::class)
        ->callAction(TestAction::make('confirmReceipt')->table($assignment), data: [
            'bukti' => ReceiptMethod::Kertas->value,
            'foto' => UploadedFile::fake()->image('bast.jpg', 1200, 1600),
        ])
        ->assertNotified('Penerimaan tercatat');

    $rows = Assignment::query()->where('no_bast', $assignment->no_bast)->get();

    expect($rows->pluck('status')->unique()->all())->toBe([AssignmentStatus::Diterima])
        ->and($rows->pluck('receipt_method')->unique()->all())->toBe([ReceiptMethod::Kertas])
        ->and($rows->pluck('receipt_path')->unique())->toHaveCount(1);
    Storage::disk(Assignment::PROOF_DISK)->assertExists($rows->first()->receipt_path);

    renderBast($assignment->refresh())
        ->assertSee('Fotonya diunggah oleh Iqbal pada 19 November 2026, 10:00')
        ->assertSeeInOrder(['LAMPIRAN: FOTO BAST YANG DITANDATANGANI', 'data:image/jpeg;base64,'], false);
    $this->get(route('filament.admin.bast.download', $assignment))->assertOk()->assertDownload();
});

test('a receipt photo must really be an image, whatever its file name says', function () {
    $assignment = waitingHandOver();

    livewire(ListAssignments::class)
        ->callAction(TestAction::make('confirmReceipt')->table($assignment), data: [
            'bukti' => ReceiptMethod::Kertas->value,
            'foto' => UploadedFile::fake()->createWithContent('bast.jpg', '<script>alert(1)</script>')->mimeType('text/html'),
        ])
        ->assertHasActionErrors(['foto']);

    expect($assignment->refresh()->status)->toBe(AssignmentStatus::Menunggu)
        ->and(Storage::disk(Assignment::PROOF_DISK)->allFiles('receipts'))->toBe([]);
});

test('an admin can instead have the employee sign a waiting hand-over on the admin\'s screen', function () {
    $assignment = waitingHandOver();

    livewire(ListAssignments::class)
        ->callAction(TestAction::make('confirmReceipt')->table($assignment), data: [
            'bukti' => ReceiptMethod::Layar->value,
            'tanda_tangan' => signatureDataUrl(),
        ])
        ->assertNotified('Penerimaan tercatat');

    expect($assignment->refresh()->receipt_method)->toBe(ReceiptMethod::Layar)
        ->and($assignment->receipt_path)->toBeNull();
    Storage::disk(Assignment::PROOF_DISK)->assertExists($assignment->signature_path);
});

test('only waiting hand-overs of assets the admin may change can be confirmed, and a late confirmation keeps no photo', function () {
    $assignment = waitingHandOver();

    Role::findByName('admin_it')->revokePermissionTo('Update:Asset');
    $this->actingAs($this->adminIt->refresh());
    livewire(ListAssignments::class)->assertActionHidden(TestAction::make('confirmReceipt')->table($assignment));

    Role::findByName('admin_it')->givePermissionTo('Update:Asset');
    $this->actingAs($this->adminIt->refresh());
    $assignment->confirmReceipt(SignaturePad::png(signatureDataUrl()), null);
    livewire(ListAssignments::class)->assertActionHidden(TestAction::make('confirmReceipt')->table($assignment->refresh()));

    expect(fn () => $assignment->confirmReceipt(null, UploadedFile::fake()->image('bast.jpg')))
        ->toThrow(LogicException::class, 'sudah diterima')
        ->and(Storage::disk(Assignment::PROOF_DISK)->allFiles('receipts'))->toBe([]);
});
