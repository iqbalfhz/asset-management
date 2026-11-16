<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AssetRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AssetRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AssetRequest');
    }

    public function view(AuthUser $authUser, AssetRequest $assetRequest): bool
    {
        return $authUser->can('View:AssetRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AssetRequest');
    }

    public function update(AuthUser $authUser, AssetRequest $assetRequest): bool
    {
        return $authUser->can('Update:AssetRequest');
    }

    public function delete(AuthUser $authUser, AssetRequest $assetRequest): bool
    {
        return $authUser->can('Delete:AssetRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AssetRequest');
    }

    public function restore(AuthUser $authUser, AssetRequest $assetRequest): bool
    {
        return $authUser->can('Restore:AssetRequest');
    }

    public function forceDelete(AuthUser $authUser, AssetRequest $assetRequest): bool
    {
        return $authUser->can('ForceDelete:AssetRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AssetRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AssetRequest');
    }

    public function replicate(AuthUser $authUser, AssetRequest $assetRequest): bool
    {
        return $authUser->can('Replicate:AssetRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AssetRequest');
    }
}
