<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Disposal;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DisposalPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Disposal');
    }

    public function view(AuthUser $authUser, Disposal $disposal): bool
    {
        return $authUser->can('View:Disposal');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Disposal');
    }

    public function update(AuthUser $authUser, Disposal $disposal): bool
    {
        return $authUser->can('Update:Disposal');
    }

    public function delete(AuthUser $authUser, Disposal $disposal): bool
    {
        return $authUser->can('Delete:Disposal');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Disposal');
    }

    public function restore(AuthUser $authUser, Disposal $disposal): bool
    {
        return $authUser->can('Restore:Disposal');
    }

    public function forceDelete(AuthUser $authUser, Disposal $disposal): bool
    {
        return $authUser->can('ForceDelete:Disposal');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Disposal');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Disposal');
    }

    public function replicate(AuthUser $authUser, Disposal $disposal): bool
    {
        return $authUser->can('Replicate:Disposal');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Disposal');
    }
}
