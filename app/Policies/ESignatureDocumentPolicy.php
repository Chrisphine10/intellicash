<?php

namespace App\Policies;

use App\Models\ESignatureDocument;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ESignatureDocumentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any documents.
     */
    public function viewAny(User $user): bool
    {
        return $user->user_type === 'admin' || $user->user_type === 'user';
    }

    /**
     * Determine whether the user can view the document.
     */
    public function view(User $user, ESignatureDocument $document): bool
    {
        return $document->tenant_id === request()->tenant->id && 
               ($user->user_type === 'admin' || 
                $user->user_type === 'user' || 
                $document->created_by === $user->id);
    }

    /**
     * Determine whether the user can create documents.
     */
    public function create(User $user): bool
    {
        return $user->user_type === 'admin' || $user->user_type === 'user';
    }

    /**
     * Determine whether the user can update the document.
     */
    public function update(User $user, ESignatureDocument $document): bool
    {
        return $document->tenant_id === request()->tenant->id && 
               in_array($document->getRawOriginal('status'), ['draft', 'sent', 'expired']) &&
               ($user->user_type === 'admin' || 
                $user->user_type === 'user' || 
                $document->created_by === $user->id);
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, ESignatureDocument $document): bool
    {
        return $document->tenant_id === request()->tenant->id && 
               $document->getRawOriginal('status') === 'draft' &&
               ($user->user_type === 'admin' || 
                $document->created_by === $user->id);
    }

    /**
     * Determine whether the user can send the document.
     */
    public function send(User $user, ESignatureDocument $document): bool
    {
        return $document->tenant_id === request()->tenant->id && 
               $document->getRawOriginal('status') === 'draft' &&
               ($user->user_type === 'admin' || 
                $user->user_type === 'user' || 
                $document->created_by === $user->id);
    }

    /**
     * Determine whether the user can cancel the document.
     */
    public function cancel(User $user, ESignatureDocument $document): bool
    {
        return $document->tenant_id === request()->tenant->id && 
               in_array($document->getRawOriginal('status'), ['sent', 'draft']) &&
               ($user->user_type === 'admin' || 
                $user->user_type === 'user' || 
                $document->created_by === $user->id);
    }

    /**
     * Determine whether the user can download the document.
     */
    public function download(User $user, ESignatureDocument $document): bool
    {
        return $document->tenant_id === request()->tenant->id && 
               ($user->user_type === 'admin' || 
                $user->user_type === 'user' || 
                $document->created_by === $user->id);
    }

    /**
     * Determine whether the user can view audit trail.
     */
    public function viewAuditTrail(User $user, ESignatureDocument $document): bool
    {
        return $document->tenant_id === request()->tenant->id && 
               ($user->user_type === 'admin' || 
                $user->user_type === 'user' || 
                $document->created_by === $user->id);
    }
}
