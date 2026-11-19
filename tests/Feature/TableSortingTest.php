<?php

use App\Models\Company;
use App\Models\Employee;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Facades\Filament;
use Filament\Tables\Columns\Column;
use Filament\Tables\Contracts\HasTable;
use Filament\Widgets\TableWidget;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
});

// Kolom relasi dan agregat diurutkan lewat subquery, jadi query yang salah langsung gagal di sini.
function sortsEveryColumn(Testable $livewire): void
{
    $columns = collect($livewire->instance()->getTable()->getColumns())->filter(fn (Column $column): bool => $column->isSortable());

    expect($columns)->not->toBeEmpty();

    foreach ($columns as $column) {
        $livewire->sortTable($column->getName())->assertSuccessful()
            ->sortTable($column->getName(), 'desc')->assertSuccessful();
    }
}

/**
 * @return list<class-string>
 */
function tableComponentsOf(string $panel): array
{
    return collect([...Filament::getPanel($panel)->getPages(), ...Filament::getPanel($panel)->getWidgets()])
        ->filter(fn (string $component): bool => is_subclass_of($component, HasTable::class) || is_subclass_of($component, TableWidget::class))
        ->values()
        ->all();
}

test('every admin list, relation manager, page and widget sorts by each of its sortable columns', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(userWithRole('super_admin'));

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        $pages = $resource::getPages();
        sortsEveryColumn(livewire($pages['index']->getPage()));

        foreach ($resource::getRelations() as $relationManager) {
            sortsEveryColumn(livewire($relationManager, [
                'ownerRecord' => $resource::getModel()::factory()->create(),
                'pageClass' => ($pages['view'] ?? $pages['edit'])->getPage(),
            ]));
        }
    }

    foreach (tableComponentsOf('admin') as $component) {
        sortsEveryColumn(livewire($component));
    }
});

test('every employee panel list sorts by each of its sortable columns', function () {
    $user = userWithRole('karyawan', 'karyawan', ['company_id' => Company::factory()->create()->id]);
    Employee::factory()->create(['company_id' => $user->company_id, 'user_id' => $user->id]);
    $this->actingAs($user);
    Filament::setCurrentPanel('karyawan');

    expect(tableComponentsOf('karyawan'))->not->toBeEmpty();

    foreach (tableComponentsOf('karyawan') as $page) {
        sortsEveryColumn(livewire($page));
    }
});
