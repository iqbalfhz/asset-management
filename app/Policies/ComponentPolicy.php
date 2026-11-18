<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Component;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ComponentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Component');
    }

    public function view(AuthUser $authUser, Component $component): bool
    {
        return $authUser->can('View:Component');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Component');
    }

    public function update(AuthUser $authUser, Component $component): bool
    {
        return $authUser->can('Update:Component');
    }

    public function delete(AuthUser $authUser, Component $component): bool
    {
        return $authUser->can('Delete:Component');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Component');
    }

    public function restore(AuthUser $authUser, Component $component): bool
    {
        return $authUser->can('Restore:Component');
    }

    public function forceDelete(AuthUser $authUser, Component $component): bool
    {
        return $authUser->can('ForceDelete:Component');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Component');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Component');
    }

    public function replicate(AuthUser $authUser, Component $component): bool
    {
        return $authUser->can('Replicate:Component');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Component');
    }
}
