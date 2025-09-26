<?php
namespace App\Models;

use App\Notifications\UserResetPasswordNotification;
use App\Traits\MultiTenant;
use App\Utilities\Overrider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail {
    use MultiTenant, HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'country_code',
        'mobile',
        'password',
        'user_type',
        'tenant_id',
        'tenant_owner',
        'status',
        'profile_picture',
        'role_id',
        'branch_id',
        'all_branch_access',
        'city',
        'state',
        'zip',
        'address',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function scopeActive($query) {
        return $query->where('status', 1);
    }

    public function scopeStaff($query) {
        return $query->where('user_type', 'admin')->orWhere('user_type', 'user');
    }

    public function role() {
        return $this->belongsTo(Role::class, 'role_id')->withDefault(['name' => _lang('Admin')]);
    }

    public function member() {
        return $this->hasOne('App\Models\Member', 'user_id')->withDefault();
    }

    public function branch() {
        return $this->belongsTo('App\Models\Branch', 'branch_id')->withDefault();
    }

    public function tenant() {
        return $this->belongsTo(Tenant::class, 'tenant_id')->withDefault();
    }

    public function sentMessages() {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages() {
        return $this->hasMany(Message::class, 'recipient_id');
    }

    protected function createdAt(): Attribute {
        $date_format = get_date_format();
        $time_format = get_time_format();

        return Attribute::make(
            get: fn($value) => \Carbon\Carbon::parse($value)->format("$date_format $time_format"),
        );
    }

    public function sendEmailVerificationNotification() {
        if (get_option('email_verification', 0) == 0) {
            return;
        }
        Overrider::load("Settings");
        $this->notify(new VerifyEmail);
    }

    public function sendPasswordResetNotification($token) {
        $this->notify(new UserResetPasswordNotification($token));
    }

    // Relationships
    public function employee()
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    /**
     * Check if user has a specific permission
     *
     * @param string|array $abilities
     * @param array $arguments
     * @return bool
     */
    public function can($abilities, $arguments = [])
    {
        // If user is tenant owner or superadmin, they have all permissions
        if ($this->tenant_owner == 1 || $this->user_type === 'superadmin') {
            return true;
        }

        // Handle array of abilities
        if (is_array($abilities)) {
            foreach ($abilities as $ability) {
                if (!$this->hasPermission($ability)) {
                    return false;
                }
            }
            return true;
        }

        // Handle single ability
        return $this->hasPermission($abilities);
    }

    /**
     * Check if user has a specific permission through their role
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        return $this->role->permissions()
            ->where('permission', $permission)
            ->exists();
    }

}
