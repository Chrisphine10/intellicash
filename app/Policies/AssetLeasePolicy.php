<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AssetLease;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetLeasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any asset leases.
     */
    public function viewAny(User $user)
    {
        return $user->hasPermission('asset.view') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can view the asset lease.
     */
    public function view(User $user, AssetLease $lease)
    {
        // Check if user belongs to the same tenant
        if ($user->tenant_id !== $lease->tenant_id) {
            return false;
        }

        return $user->hasPermission('asset.view') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can create asset leases.
     */
    public function create(User $user)
    {
        return $user->hasPermission('asset.create') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can update the asset lease.
     */
    public function update(User $user, AssetLease $lease)
    {
        // Check if user belongs to the same tenant
        if ($user->tenant_id !== $lease->tenant_id) {
            return false;
        }

        // Only allow updates to active leases
        if ($lease->status !== 'active') {
            return false;
        }

        return $user->hasPermission('asset.edit') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can delete the asset lease.
     */
    public function delete(User $user, AssetLease $lease)
    {
        // Check if user belongs to the same tenant
        if ($user->tenant_id !== $lease->tenant_id) {
            return false;
        }

        // Don't allow deletion of completed leases
        if ($lease->status === 'completed') {
            return false;
        }

        return $user->hasPermission('asset.delete') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can manage lease status.
     */
    public function manageStatus(User $user, AssetLease $lease)
    {
        // Check if user belongs to the same tenant
        if ($user->tenant_id !== $lease->tenant_id) {
            return false;
        }

        return $user->hasPermission('asset.edit') || $user->user_type === 'admin';
    }
}
