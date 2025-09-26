<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AssetCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetCategoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any asset categories.
     */
    public function viewAny(User $user)
    {
        return $user->hasPermission('asset.view') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can view the asset category.
     */
    public function view(User $user, AssetCategory $category)
    {
        // Check if user belongs to the same tenant
        if ($user->tenant_id !== $category->tenant_id) {
            return false;
        }

        return $user->hasPermission('asset.view') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can create asset categories.
     */
    public function create(User $user)
    {
        return $user->hasPermission('asset.create') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can update the asset category.
     */
    public function update(User $user, AssetCategory $category)
    {
        // Check if user belongs to the same tenant
        if ($user->tenant_id !== $category->tenant_id) {
            return false;
        }

        return $user->hasPermission('asset.edit') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can delete the asset category.
     */
    public function delete(User $user, AssetCategory $category)
    {
        // Check if user belongs to the same tenant
        if ($user->tenant_id !== $category->tenant_id) {
            return false;
        }

        // Don't allow deletion if category has assets
        if ($category->assets()->count() > 0) {
            return false;
        }

        return $user->hasPermission('asset.delete') || $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can toggle category status.
     */
    public function toggleStatus(User $user, AssetCategory $category)
    {
        // Check if user belongs to the same tenant
        if ($user->tenant_id !== $category->tenant_id) {
            return false;
        }

        return $user->hasPermission('asset.edit') || $user->user_type === 'admin';
    }
}
