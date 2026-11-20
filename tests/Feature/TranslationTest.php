<?php

use App\Enums\Language;
use App\Filament\Karyawan\Pages\MyAssets;
use App\Filament\Resources\Assets\AssetResource;
use App\Http\Middleware\UseIndonesianForDocuments;
use App\Models\AssetRequest;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Notifications\Notice;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

/**
 * Semua teks literal di __('...') dalam kode dan view, kecuali kunci terjemahan paket (berisi ::).
 *
 * @param  list<string>  $functions
 * @return array<int, string>
 */
function interfaceTexts(array $functions = ['__', 'trans', 'trans_choice']): array
{
    $texts = [];
    $files = collect(['app', 'routes', 'resources/views'])
        ->flatMap(fn (string $dir) => iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS))))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.php'));

    foreach ($files as $file) {
        $code = file_get_contents($file->getPathname());
        $tokens = token_get_all(str_ends_with($file->getFilename(), '.blade.php') ? Blade::compileString($code) : $code);

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || ! in_array($token[1], $functions, true)) {
                continue;
            }

            $next = array_values(array_filter(array_slice($tokens, $index + 1, 4), fn ($t): bool => ! is_array($t) || $t[0] !== T_WHITESPACE));

            if (($next[0] ?? null) === '(' && is_array($next[1] ?? null) && $next[1][0] === T_CONSTANT_ENCAPSED_STRING) {
                $texts[] = eval('return '.$next[1][1].';');
            }
        }
    }

    return array_values(array_unique(array_filter($texts, fn (string $text): bool => ! str_contains($text, '::'))));
}

function placeholdersOf(string $text): array
{
    preg_match_all('/:[a-zA-Z_]+/', $text, $matches);
    $placeholders = array_unique($matches[0]);
    sort($placeholders);

    return $placeholders;
}

test('every interface text has an English translation with the same placeholders, and nothing unused is kept', function () {
    $english = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
    $texts = interfaceTexts();

    expect(array_values(array_diff($texts, array_keys($english))))->toBe([], 'Teks tanpa terjemahan Inggris')
        ->and(array_values(array_diff(array_keys($english), $texts)))->toBe([], 'Terjemahan yang tidak dipakai lagi');

    foreach ($english as $text => $translation) {
        expect(placeholdersOf($translation))->toBe(placeholdersOf($text), "Placeholder berbeda: {$text}");
    }
});

// trans_choice memakai bahasa cadangan (Inggris) kalau kuncinya tidak ada di id.json.
test('plural labels stay Indonesian for Indonesian users', function () {
    $indonesian = json_decode(file_get_contents(lang_path('id.json')), true, flags: JSON_THROW_ON_ERROR);

    expect(array_values(array_diff(interfaceTexts(['trans_choice']), array_keys($indonesian))))->toBe([])
        ->and(AssetResource::getPluralModelLabel())->toBe('Aset');
});

test('a user who picks English sees the panels in English, including core asset statuses', function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    $tcm = Company::factory()->create();
    $laptops = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    availableAsset($tcm, $laptops);
    $admin = userWithRole('super_admin', attributes: ['company_id' => $tcm->id, 'locale' => Language::English]);

    $this->actingAs($admin)->get(AssetResource::getUrl('index', panel: 'admin'))
        ->assertOk()
        ->assertSee(['Holder', 'Available', 'Hand-overs'])
        ->assertDontSee('Tersedia');

    $this->get(MyAssets::getUrl(panel: 'karyawan'))->assertOk()->assertSee('My assets');

    $admin->update(['locale' => Language::Indonesia]);

    $this->get(AssetResource::getUrl('index', panel: 'admin'))->assertOk()->assertSee('Tersedia');
});

test('an email is written in the language of the person receiving it, not the one who caused it', function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Notification::fake();
    Filament::setCurrentPanel('admin');
    $tcm = Company::factory()->create();
    $laptops = Category::factory()->create(['nama' => 'Laptop', 'role_id' => Role::findByName('admin_it')->id]);
    $englishAdmin = userWithRole('admin_it', attributes: ['company_id' => $tcm->id, 'locale' => Language::English]);
    $indonesianAdmin = userWithRole('admin_it', attributes: ['company_id' => $tcm->id]);
    availableAsset($tcm, $laptops, ['requestable' => true]);

    AssetRequest::submit(Employee::factory()->create(['company_id' => $tcm->id, 'nama' => 'Rina']), $laptops, 'Laptop lama sering mati');

    Notification::assertSentTo($englishAdmin, Notice::class, fn (Notice $notice): bool => $notice->subject === 'New asset request: Laptop'
        && $notice->intro === 'Rina requested a Laptop asset.');
    Notification::assertSentTo($indonesianAdmin, Notice::class, fn (Notice $notice): bool => $notice->subject === 'Permintaan aset baru: Laptop');
});

test('official documents always use Indonesian', function (string $route) {
    expect(Route::getRoutes()->getByName($route)->gatherMiddleware())->toContain(UseIndonesianForDocuments::class);
})->with([
    'filament.admin.bast.download',
    'filament.karyawan.bast.download',
    'filament.admin.disposals.report',
    'filament.admin.audits.report',
    'filament.admin.labels',
]);
