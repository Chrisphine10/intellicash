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

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
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
