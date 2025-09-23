<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class VslaSetting extends Model
{
    use MultiTenant;

    protected $fillable = [
        'tenant_id',
        'share_amount',
        'min_shares_per_member',
        'max_shares_per_member', 
        'max_shares_per_meeting',
        'penalty_amount',
        'welfare_amount',
        'meeting_frequency',
        'meeting_day_of_week',
        'meeting_days',
        'meeting_time',
        'auto_approve_loans',
        'max_loan_amount',
        'max_loan_duration_days',
        'create_default_loan_product',
        'create_default_savings_products',
        'create_default_bank_accounts',
        'create_default_expense_categories',
        'auto_create_member_accounts',
    ];

    protected $casts = [
        'share_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'welfare_amount' => 'decimal:2',
        'auto_approve_loans' => 'boolean',
        'max_loan_amount' => 'decimal:2',
        'meeting_days' => 'array',
        'create_default_loan_product' => 'boolean',
        'create_default_savings_products' => 'boolean',
        'create_default_bank_accounts' => 'boolean',
        'create_default_expense_categories' => 'boolean',
        'auto_create_member_accounts' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get formatted meeting time for display
     */
    public function getFormattedMeetingTime()
    {
        if (!$this->meeting_time) {
            return '10:00';
        }
        
        // If it's already in HH:MM:SS format, just extract HH:MM
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $this->meeting_time)) {
            return substr($this->meeting_time, 0, 5);
        }
        
        try {
            return \Carbon\Carbon::parse($this->meeting_time)->format('H:i');
        } catch (\Exception $e) {
            return '10:00';
        }
    }

    /**
     * Get meeting days as array
     */
    public function getMeetingDaysArray()
    {
        return $this->meeting_days ?? [];
    }

    /**
     * Check if a specific day is a meeting day
     */
    public function isMeetingDay($day)
    {
        return in_array($day, $this->getMeetingDaysArray());
    }

    /**
     * Get formatted meeting days string
     */
    public function getMeetingDaysString()
    {
        $days = $this->getMeetingDaysArray();
        if (empty($days)) {
            return 'No meeting days set';
        }
        
        $dayNames = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday', 
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday'
        ];
        
        $formattedDays = array_map(function($day) use ($dayNames) {
            return $dayNames[$day] ?? ucfirst($day);
        }, $days);
        
        return implode(', ', $formattedDays);
    }

    /**
     * Get next meeting date based on current settings
     */
    public function getNextMeetingDate()
    {
        $now = now();
        $meetingDays = $this->getMeetingDaysArray();
        
        if (empty($meetingDays)) {
            return null;
        }
        
        $dayMap = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 0
        ];
        
        $nextMeeting = null;
        foreach ($meetingDays as $day) {
            $dayNumber = $dayMap[$day] ?? null;
            if ($dayNumber !== null) {
                $candidate = $now->copy()->next($dayNumber);
                if ($nextMeeting === null || $candidate->lt($nextMeeting)) {
                    $nextMeeting = $candidate;
                }
            }
        }
        
        return $nextMeeting;
    }

    /**
     * Get all chairpersons
     */
    public function getChairpersons()
    {
        return \App\Models\Member::where('tenant_id', $this->tenant_id)
            ->whereHas('activeVslaRoleAssignments', function($query) {
                $query->where('role', 'chairperson');
            })
            ->get();
    }

    /**
     * Get all treasurers
     */
    public function getTreasurers()
    {
        return \App\Models\Member::where('tenant_id', $this->tenant_id)
            ->whereHas('activeVslaRoleAssignments', function($query) {
                $query->where('role', 'treasurer');
            })
            ->get();
    }

    /**
     * Get all secretaries
     */
    public function getSecretaries()
    {
        return \App\Models\Member::where('tenant_id', $this->tenant_id)
            ->whereHas('activeVslaRoleAssignments', function($query) {
                $query->where('role', 'secretary');
            })
            ->get();
    }

    /**
     * Get all VSLA role holders by role
     */
    public function getRoleHolders($role = null)
    {
        if ($role) {
            return \App\Models\Member::where('tenant_id', $this->tenant_id)
                ->whereHas('activeVslaRoleAssignments', function($query) use ($role) {
                    $query->where('role', $role);
                })
                ->get();
        }

        return [
            'chairperson' => $this->getChairpersons(),
            'treasurer' => $this->getTreasurers(),
            'secretary' => $this->getSecretaries(),
        ];
    }

    /**
     * Get all members with any VSLA role
     */
    public function getAllRoleHolders()
    {
        return \App\Models\Member::where('tenant_id', $this->tenant_id)
            ->whereHas('activeVslaRoleAssignments')
            ->with('activeVslaRoleAssignments')
            ->get();
    }
}
