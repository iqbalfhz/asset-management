<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Depreciation;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DepreciationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Depreciation');
    }

    public function view(AuthUser $authUser, Depreciation $depreciation): bool
    {
        return $authUser->can('View:Depreciation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Depreciation');
    }

    public function update(AuthUser $authUser, Depreciation $depreciation): bool
    {
        return $authUser->can('Update:Depreciation');
    }

    public function delete(AuthUser $authUser, Depreciation $depreciation): bool
    {
        return $authUser->can('Delete:Depreciation');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Depreciation');
    }

    public function restore(AuthUser $authUser, Depreciation $depreciation): bool
    {
        return $authUser->can('Restore:Depreciation');
    }

    public function forceDelete(AuthUser $authUser, Depreciation $depreciation): bool
    {
        return $authUser->can('ForceDelete:Depreciation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Depreciation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Depreciation');
    }

    public function replicate(AuthUser $authUser, Depreciation $depreciation): bool
    {
        return $authUser->can('Replicate:Depreciation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Depreciation');
    }
}
