<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Asset;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any assets.
     */
    public function viewAny(User $user)
    {
        return $user->hasPermission('asset.view') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can view the asset.
     */
    public function view(User $user, Asset $asset)
    {
        // Check if user belongs to the same tenant
        if ($user->tenant_id !== $asset->tenant_id) {
            return false;
        }

        return $user->hasPermission('asset.view') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can create assets.
     */
    public function create(User $user)
    {
        return $user->hasPermission('asset.create') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can update the asset.
     */
    public function update(User $user, Asset $asset)
    {
        // Check if user belongs to the same tenant
        if ($user->tenant_id !== $asset->tenant_id) {
            return false;
        }

        return $user->hasPermission('asset.edit') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can delete the asset.
     */
    public function delete(User $user, Asset $asset)
    {
        // Check if user belongs to the same tenant
        if ($user->tenant_id !== $asset->tenant_id) {
            return false;
        }

        // Don't allow deletion if asset has active leases
        if ($asset->activeLeases()->count() > 0) {
            return false;
        }

        return $user->hasPermission('asset.delete') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can lease the asset.
     */
    public function lease(User $user, Asset $asset)
    {
        // Check if user belongs to the same tenant
        if ($user->tenant_id !== $asset->tenant_id) {
            return false;
        }

        // Check if asset is available for lease
        if (!$asset->isAvailableForLease()) {
            return false;
        }

        return $user->hasPermission('asset.create') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can manage asset leases.
     */
    public function manageLease(User $user, Asset $asset)
    {
        // Check if user belongs to the same tenant
        if ($user->tenant_id !== $asset->tenant_id) {
            return false;
        }

        return $user->hasPermission('asset.edit') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can manage asset maintenance.
     */
    public function manageMaintenance(User $user, Asset $asset)
    {
        // Check if user belongs to the same tenant
        if ($user->tenant_id !== $asset->tenant_id) {
            return false;
        }

        return $user->hasPermission('asset.edit') || $user->user_type === 'admin';
    }
}
