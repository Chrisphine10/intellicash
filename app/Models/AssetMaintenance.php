<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class AssetMaintenance extends Model
{
    use HasFactory, MultiTenant;

    protected $table = 'asset_maintenances';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'maintenance_type',
        'title',
        'description',
        'scheduled_date',
        'completed_date',
        'cost',
        'status',
        'notes',
        'performed_by',
        'created_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_date' => 'date',
        'cost' => 'decimal:2',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'scheduled')
                    ->where('scheduled_date', '<', now());
    }

    public function scopeByType($query, $type)
    {
        return $query->where('maintenance_type', $type);
    }

    public function scopeByAsset($query, $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    protected function scheduledDate(): Attribute
    {
        $date_format = get_date_format();
        return Attribute::make(
            get: fn($value) => \Carbon\Carbon::parse($value)->format("$date_format"),
        );
    }

    protected function completedDate(): Attribute
    {
        $date_format = get_date_format();
        return Attribute::make(
            get: fn($value) => $value ? \Carbon\Carbon::parse($value)->format("$date_format") : null,
        );
    }

    public function getIsOverdueAttribute()
    {
        return $this->status === 'scheduled' && 
               \Carbon\Carbon::parse($this->scheduled_date)->isPast();
    }

    public function getDurationAttribute()
    {
        if (!$this->completed_date) {
            return null;
        }

        return \Carbon\Carbon::parse($this->scheduled_date)
            ->diffInDays($this->completed_date);
    }

    public function markAsInProgress()
    {
        $this->status = 'in_progress';
        $this->save();
    }

    public function markAsCompleted($notes = null, $performedBy = null)
    {
        $this->status = 'completed';
        $this->completed_date = now();
        
        if ($notes) {
            $this->notes = $notes;
        }
        
        if ($performedBy) {
            $this->performed_by = $performedBy;
        }
        
        $this->save();
    }

    public function cancel()
    {
        $this->status = 'cancelled';
        $this->save();
    }

    public static function getMaintenanceTypes()
    {
        return [
            'scheduled' => 'Scheduled Maintenance',
            'emergency' => 'Emergency Repair',
            'repair' => 'Repair',
            'inspection' => 'Inspection',
            'cleaning' => 'Cleaning',
            'upgrade' => 'Upgrade',
        ];
    }

    public static function getStatuses()
    {
        return [
            'scheduled' => 'Scheduled',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }
}
