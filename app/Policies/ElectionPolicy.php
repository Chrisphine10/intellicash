<?php

namespace App\Policies;

use App\Models\Election;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ElectionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any elections.
     */
    public function viewAny(User $user)
    {
        return $user->user_type === 'admin' || $user->user_type === 'user';
    }

    /**
     * Determine whether the user can view the election.
     */
    public function view(User $user, Election $election)
    {
        return $election->tenant_id === app('tenant')->id;
    }

    /**
     * Determine whether the user can create elections.
     */
    public function create(User $user)
    {
        return $user->user_type === 'admin';
    }

    /**
     * Determine whether the user can update the election.
     */
    public function update(User $user, Election $election)
    {
        return $user->user_type === 'admin' && 
               $election->tenant_id === app('tenant')->id && 
               $election->status === 'draft';
    }

    /**
     * Determine whether the user can manage the election.
     */
    public function manage(User $user, Election $election)
    {
        return $user->user_type === 'admin' && 
               $election->tenant_id === app('tenant')->id;
    }

    /**
     * Determine whether the user can vote in the election.
     */
    public function vote(User $user, Election $election)
    {
        // Check if user is a member of the tenant
        $member = \App\Models\Member::where('user_id', $user->id)
            ->where('tenant_id', $election->tenant_id)
            ->first();

        if (!$member) {
            return false;
        }

        // Check if election is active and within voting period
        if (!$election->canVote()) {
            return false;
        }

        // Check if user has already voted
        $existingVote = \App\Models\Vote::where('election_id', $election->id)
            ->where('member_id', $member->id)
            ->first();

        return !$existingVote;
    }

    /**
     * Determine whether the user can delete the election.
     */
    public function delete(User $user, Election $election)
    {
        return $user->user_type === 'admin' && 
               $election->tenant_id === app('tenant')->id && 
               $election->status === 'draft';
    }
}
