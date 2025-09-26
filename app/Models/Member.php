<?php
namespace App\Models;

use App\Traits\Branch;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Member extends Model {
    use Notifiable, MultiTenant, Branch;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'members';

    protected $fillable = [
        'first_name',
        'last_name',
        'branch_id',
        'email',
        'country_code',
        'mobile',
        'business_name',
        'member_no',
        'gender',
        'city',
        'county',
        'zip',
        'address',
        'credit_source',
        'photo',
        'custom_fields',
        'vsla_role',
    ];

    // SECURE: Protected fields that should not be mass assignable
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'tenant_id',        // CRITICAL: Prevent tenant switching
        'user_id',         // SECURE: Prevent user account takeover
        'status',          // SECURE: Prevent unauthorized status changes
        'is_vsla_chairperson',  // SECURE: Prevent privilege escalation
        'is_vsla_treasurer',    // SECURE: Prevent privilege escalation
        'is_vsla_secretary',    // SECURE: Prevent privilege escalation
    ];

    protected $casts = [
        'is_vsla_chairperson' => 'boolean',
        'is_vsla_treasurer' => 'boolean',
        'is_vsla_secretary' => 'boolean',
    ];

    protected static function booted() {
        // Add tenant isolation global scope
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check() && auth()->user()->tenant_id) {
                $builder->where('tenant_id', auth()->user()->tenant_id);
            }
        });

        // Add status filtering with tenant validation
        static::addGlobalScope('status', function (Builder $builder) {
            return $builder->where('status', 1);
        });

        // Add security event monitoring
        static::created(function ($member) {
            if (class_exists('App\Services\ThreatMonitoringService')) {
                app('App\Services\ThreatMonitoringService')->monitorEvent('member_created', [
                    'member_id' => $member->id,
                    'member_no' => $member->member_no,
                    'tenant_id' => $member->tenant_id,
                    'user_id' => auth()->id()
                ]);
            }
        });

        static::updated(function ($member) {
            if (class_exists('App\Services\ThreatMonitoringService')) {
                app('App\Services\ThreatMonitoringService')->monitorEvent('member_updated', [
                    'member_id' => $member->id,
                    'member_no' => $member->member_no,
                    'tenant_id' => $member->tenant_id,
                    'user_id' => auth()->id()
                ]);
            }
        });
    }

    public function getCreatedAtAttribute($value) {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

    public function getNameAttribute() {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function branch() {
        return $this->belongsTo('App\Models\Branch', 'branch_id')->withDefault();
    }

    public function user() {
        return $this->belongsTo('App\Models\User', 'user_id')->withDefault();
    }

    public function employee() {
        return $this->hasOne('App\Models\Employee', 'member_id');
    }

    public function loans() {
        return $this->hasMany('App\Models\Loan', 'borrower_id');
    }

    public function savings_accounts() {
        return $this->hasMany('App\Models\SavingsAccount', 'member_id');
    }

    public function transactions() {
        return $this->hasMany('App\Models\Transaction', 'member_id');
    }

    public function documents() {
        return $this->hasMany('App\Models\MemberDocument', 'member_id');
    }

    // VSLA Role Methods
    public function vslaRoleAssignments()
    {
        return $this->hasMany(VslaRoleAssignment::class);
    }

    public function activeVslaRoleAssignments()
    {
        return $this->hasMany(VslaRoleAssignment::class)->where('is_active', true);
    }

    public function isVslaChairperson() {
        return $this->activeVslaRoleAssignments()->where('role', 'chairperson')->exists();
    }

    public function isVslaTreasurer() {
        return $this->activeVslaRoleAssignments()->where('role', 'treasurer')->exists();
    }

    public function isVslaSecretary() {
        return $this->activeVslaRoleAssignments()->where('role', 'secretary')->exists();
    }

    public function getVslaRoles() {
        return $this->activeVslaRoleAssignments->pluck('role')->toArray();
    }

    public function getVslaRoleNames() {
        $roles = $this->getVslaRoles();
        return array_map('ucfirst', $roles);
    }

    public function hasVslaRole($role) {
        return in_array($role, $this->getVslaRoles());
    }

    public function scopeVslaChairperson($query) {
        return $query->whereHas('activeVslaRoleAssignments', function($q) {
            $q->where('role', 'chairperson');
        });
    }

    public function scopeVslaTreasurer($query) {
        return $query->whereHas('activeVslaRoleAssignments', function($q) {
            $q->where('role', 'treasurer');
        });
    }

    public function scopeVslaSecretary($query) {
        return $query->whereHas('activeVslaRoleAssignments', function($q) {
            $q->where('role', 'secretary');
        });
    }

    public function scopeWithVslaRole($query, $role) {
        return $query->whereHas('activeVslaRoleAssignments', function($q) use ($role) {
            $q->where('role', $role);
        });
    }

    public function scopeActive($query) {
        return $query->where('status', 1);
    }

    public function leaseRequests() {
        return $this->hasMany('App\Models\LeaseRequest', 'member_id');
    }

    public function assetLeases() {
        return $this->hasMany('App\Models\AssetLease', 'member_id');
    }

    /**
     * Check if member can be deleted
     */
    public function canBeDeleted() {
        return $this->loans()->where('status', 'active')->count() === 0 &&
               $this->transactions()->where('trans_date', '>=', now()->subDays(30))->count() === 0 &&
               $this->savings_accounts()->where('status', 1)->where('balance', '>', 0)->count() === 0;
    }

    /**
     * Get member statistics
     */
    public function getTotalLoansAttribute() {
        return $this->loans()->sum('applied_amount');
    }

    public function getActiveLoansAttribute() {
        return $this->loans()->where('status', 1)->count(); // Use integer status consistently
    }

    public function getTotalSavingsAttribute() {
        return $this->savings_accounts()->sum('balance');
    }


    /**
     * Scope for inactive members only
     */
    public function scopeInactive($query) {
        return $query->where('status', 0);
    }
}