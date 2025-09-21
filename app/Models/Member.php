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

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $fillable = [
        'first_name',
        'last_name',
        'branch_id',
        'user_id',
        'status',
        'email',
        'country_code',
        'mobile',
        'business_name',
        'member_no',
        'gender',
        'city',
        'state',
        'zip',
        'address',
        'credit_source',
        'photo',
        'custom_fields',
        'tenant_id',
        'vsla_role',
        'is_vsla_chairperson',
        'is_vsla_treasurer',
        'is_vsla_secretary',
    ];

    protected $casts = [
        'is_vsla_chairperson' => 'boolean',
        'is_vsla_treasurer' => 'boolean',
        'is_vsla_secretary' => 'boolean',
    ];

    protected static function booted() {
        static::addGlobalScope('status', function (Builder $builder) {
            return $builder->where('status', 1);
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

    public function loans() {
        return $this->hasMany('App\Models\Loan', 'borrower_id');
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
}