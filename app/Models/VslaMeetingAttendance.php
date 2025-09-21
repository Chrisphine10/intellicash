<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class VslaMeetingAttendance extends Model
{
    use MultiTenant;

    protected $table = 'vsla_meeting_attendance';

    protected $fillable = [
        'meeting_id',
        'member_id',
        'present',
        'notes',
    ];

    protected $casts = [
        'present' => 'boolean',
    ];

    public function meeting()
    {
        return $this->belongsTo(VslaMeeting::class, 'meeting_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
