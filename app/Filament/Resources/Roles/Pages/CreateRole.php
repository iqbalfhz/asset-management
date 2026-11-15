<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateRole extends CreateRecord
{
    use SyncsPermissions;

    protected static string $resource = RoleResource::class;

    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->separatePermissions($data);
    }

    protected function afterCreate(): void
    {
        $this->syncPermissions();
    }
}
