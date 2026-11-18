<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Accessory;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AccessoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Accessory');
    }

    public function view(AuthUser $authUser, Accessory $accessory): bool
    {
        return $authUser->can('View:Accessory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Accessory');
    }

    public function update(AuthUser $authUser, Accessory $accessory): bool
    {
        return $authUser->can('Update:Accessory');
    }

    public function delete(AuthUser $authUser, Accessory $accessory): bool
    {
        return $authUser->can('Delete:Accessory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Accessory');
    }

    public function restore(AuthUser $authUser, Accessory $accessory): bool
    {
        return $authUser->can('Restore:Accessory');
    }

    public function forceDelete(AuthUser $authUser, Accessory $accessory): bool
    {
        return $authUser->can('ForceDelete:Accessory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Accessory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Accessory');
    }

    public function replicate(AuthUser $authUser, Accessory $accessory): bool
    {
        return $authUser->can('Replicate:Accessory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Accessory');
    }
}
