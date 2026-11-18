<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Kit;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class KitPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Kit');
    }

    public function view(AuthUser $authUser, Kit $kit): bool
    {
        return $authUser->can('View:Kit');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Kit');
    }

    public function update(AuthUser $authUser, Kit $kit): bool
    {
        return $authUser->can('Update:Kit');
    }

    public function delete(AuthUser $authUser, Kit $kit): bool
    {
        return $authUser->can('Delete:Kit');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Kit');
    }

    public function restore(AuthUser $authUser, Kit $kit): bool
    {
        return $authUser->can('Restore:Kit');
    }

    public function forceDelete(AuthUser $authUser, Kit $kit): bool
    {
        return $authUser->can('ForceDelete:Kit');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Kit');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Kit');
    }

    public function replicate(AuthUser $authUser, Kit $kit): bool
    {
        return $authUser->can('Replicate:Kit');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Kit');
    }
}
