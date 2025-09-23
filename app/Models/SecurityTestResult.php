<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityTestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'test_type',
        'test_results',
        'test_summary',
        'total_tests',
        'passed_tests',
        'failed_tests',
        'success_rate',
        'duration_seconds',
        'test_started_at',
        'test_completed_at',
    ];

    protected $casts = [
        'test_results' => 'array',
        'test_summary' => 'array',
        'success_rate' => 'float',
        'test_started_at' => 'datetime',
        'test_completed_at' => 'datetime',
    ];

    /**
     * Get the user who ran the test
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute()
    {
        if ($this->duration_seconds < 60) {
            return $this->duration_seconds . 's';
        } elseif ($this->duration_seconds < 3600) {
            return round($this->duration_seconds / 60, 1) . 'm';
        } else {
            return round($this->duration_seconds / 3600, 1) . 'h';
        }
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute()
    {
        if ($this->success_rate >= 90) {
            return 'bg-success';
        } elseif ($this->success_rate >= 75) {
            return 'bg-warning';
        } else {
            return 'bg-danger';
        }
    }

    /**
     * Get status text
     */
    public function getStatusTextAttribute()
    {
        if ($this->success_rate >= 90) {
            return 'Excellent';
        } elseif ($this->success_rate >= 75) {
            return 'Good';
        } else {
            return 'Needs Attention';
        }
    }
}