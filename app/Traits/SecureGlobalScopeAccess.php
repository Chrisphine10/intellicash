<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait SecureGlobalScopeAccess
{
    /**
     * Securely bypass global scopes with proper authorization
     * 
     * @param array $scopes
     * @return Builder
     * @throws \Exception
     */
    public function secureWithoutGlobalScopes(array $scopes = [])
    {
        // Check if user has permission to bypass global scopes
        if (!$this->canBypassGlobalScopes()) {
            Log::warning('Unauthorized global scope bypass attempt', [
                'user_id' => Auth::id(),
                'model' => get_class($this),
                'scopes' => $scopes,
                'ip' => request()->ip()
            ]);
            
            throw new \Exception('Unauthorized access: Global scope bypass not allowed');
        }

        // Log authorized bypass
        Log::info('Authorized global scope bypass', [
            'user_id' => Auth::id(),
            'model' => get_class($this),
            'scopes' => $scopes
        ]);

        return $this->withoutGlobalScopes($scopes);
    }

    /**
     * Check if current user can bypass global scopes
     * 
     * @return bool
     */
    private function canBypassGlobalScopes(): bool
    {
        $user = Auth::user();
        
        if (!$user) {
            return false;
        }

        // Only super admins and system administrators can bypass global scopes
        $allowedRoles = ['superadmin', 'system_admin'];
        
        return in_array($user->user_type, $allowedRoles);
    }

    /**
     * Securely find record without global scopes
     * 
     * @param mixed $id
     * @param array $scopes
     * @return mixed
     * @throws \Exception
     */
    public function secureFindWithoutScopes($id, array $scopes = [])
    {
        if (!$this->canBypassGlobalScopes()) {
            throw new \Exception('Unauthorized access: Cannot bypass global scopes');
        }

        return $this->withoutGlobalScopes($scopes)->find($id);
    }

    /**
     * Securely get all records without global scopes
     * 
     * @param array $scopes
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws \Exception
     */
    public function secureAllWithoutScopes(array $scopes = [])
    {
        if (!$this->canBypassGlobalScopes()) {
            throw new \Exception('Unauthorized access: Cannot bypass global scopes');
        }

        return $this->withoutGlobalScopes($scopes)->get();
    }
}
