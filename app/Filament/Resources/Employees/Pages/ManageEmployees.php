<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Imports\EmployeeImporter;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployees extends ManageRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(EmployeeImporter::class)
                ->authorize('create', Employee::class),
            CreateAction::make(),
        ];
    }
}
