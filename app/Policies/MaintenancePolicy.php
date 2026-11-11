<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Maintenance;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MaintenancePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Maintenance');
    }

    public function view(AuthUser $authUser, Maintenance $maintenance): bool
    {
        return $authUser->can('View:Maintenance');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Maintenance');
    }

    public function update(AuthUser $authUser, Maintenance $maintenance): bool
    {
        return $authUser->can('Update:Maintenance');
    }

    public function delete(AuthUser $authUser, Maintenance $maintenance): bool
    {
        return $authUser->can('Delete:Maintenance');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Maintenance');
    }

    public function restore(AuthUser $authUser, Maintenance $maintenance): bool
    {
        return $authUser->can('Restore:Maintenance');
    }

    public function forceDelete(AuthUser $authUser, Maintenance $maintenance): bool
    {
        return $authUser->can('ForceDelete:Maintenance');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Maintenance');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Maintenance');
    }

    public function replicate(AuthUser $authUser, Maintenance $maintenance): bool
    {
        return $authUser->can('Replicate:Maintenance');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Maintenance');
    }
}
