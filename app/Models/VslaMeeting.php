<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class VslaMeeting extends Model
{
    use MultiTenant;

    protected $table = 'vsla_meetings';

    protected $fillable = [
        'tenant_id',
        'cycle_id',
        'meeting_number',
        'meeting_date',
        'meeting_time',
        'agenda',
        'notes',
        'status',
        'created_user_id',
    ];

    protected $casts = [
        'meeting_date' => 'date',
        'meeting_time' => 'datetime:H:i',
    ];

    protected static function boot()
    {
        parent::boot();

        // Ensure meeting is assigned to the correct cycle based on date
        static::creating(function ($meeting) {
            static::assignToCorrectCycle($meeting);
        });

        static::updating(function ($meeting) {
            if ($meeting->isDirty('meeting_date') || $meeting->isDirty('cycle_id')) {
                static::assignToCorrectCycle($meeting);
            }
        });
    }

    /**
     * Assign meeting to the correct cycle based on meeting date
     */
    protected static function assignToCorrectCycle($meeting)
    {
        if (!$meeting->cycle_id) {
            // Find the active cycle for this tenant that contains the meeting date
            $cycle = VslaCycle::where('tenant_id', $meeting->tenant_id)
                ->where('status', 'active')
                ->where('start_date', '<=', $meeting->meeting_date)
                ->where(function($query) use ($meeting) {
                    $query->where('end_date', '>=', $meeting->meeting_date)
                          ->orWhereNull('end_date');
                })
                ->first();

            if ($cycle) {
                $meeting->cycle_id = $cycle->id;
            } else {
                // Check if there's a completed cycle that could contain this meeting
                $completedCycle = VslaCycle::where('tenant_id', $meeting->tenant_id)
                    ->where('status', 'completed')
                    ->where('start_date', '<=', $meeting->meeting_date)
                    ->where('end_date', '>=', $meeting->meeting_date)
                    ->first();

                if ($completedCycle) {
                    $meeting->cycle_id = $completedCycle->id;
                } else {
                    throw new \Exception('No cycle found for the meeting date. Please ensure there is an active or completed cycle covering this period.');
                }
            }
        }
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cycle()
    {
        return $this->belongsTo(VslaCycle::class, 'cycle_id');
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    public function attendance()
    {
        return $this->hasMany(VslaMeetingAttendance::class, 'meeting_id');
    }

    public function transactions()
    {
        return $this->hasMany(VslaTransaction::class, 'meeting_id');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }
}