<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AssetBooking;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AssetBookingPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AssetBooking');
    }

    public function view(AuthUser $authUser, AssetBooking $assetBooking): bool
    {
        return $authUser->can('View:AssetBooking');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AssetBooking');
    }

    public function update(AuthUser $authUser, AssetBooking $assetBooking): bool
    {
        return $authUser->can('Update:AssetBooking');
    }

    public function delete(AuthUser $authUser, AssetBooking $assetBooking): bool
    {
        return $authUser->can('Delete:AssetBooking');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AssetBooking');
    }

    public function restore(AuthUser $authUser, AssetBooking $assetBooking): bool
    {
        return $authUser->can('Restore:AssetBooking');
    }

    public function forceDelete(AuthUser $authUser, AssetBooking $assetBooking): bool
    {
        return $authUser->can('ForceDelete:AssetBooking');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AssetBooking');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AssetBooking');
    }

    public function replicate(AuthUser $authUser, AssetBooking $assetBooking): bool
    {
        return $authUser->can('Replicate:AssetBooking');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AssetBooking');
    }
}
