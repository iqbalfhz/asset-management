<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Manufacturer;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ManufacturerPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Manufacturer');
    }

    public function view(AuthUser $authUser, Manufacturer $manufacturer): bool
    {
        return $authUser->can('View:Manufacturer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Manufacturer');
    }

    public function update(AuthUser $authUser, Manufacturer $manufacturer): bool
    {
        return $authUser->can('Update:Manufacturer');
    }

    public function delete(AuthUser $authUser, Manufacturer $manufacturer): bool
    {
        return $authUser->can('Delete:Manufacturer');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Manufacturer');
    }

    public function restore(AuthUser $authUser, Manufacturer $manufacturer): bool
    {
        return $authUser->can('Restore:Manufacturer');
    }

    public function forceDelete(AuthUser $authUser, Manufacturer $manufacturer): bool
    {
        return $authUser->can('ForceDelete:Manufacturer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Manufacturer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Manufacturer');
    }

    public function replicate(AuthUser $authUser, Manufacturer $manufacturer): bool
    {
        return $authUser->can('Replicate:Manufacturer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Manufacturer');
    }
}
