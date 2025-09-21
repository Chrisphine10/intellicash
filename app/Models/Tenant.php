<?php
namespace App\Models;

use Database\Seeders\SaasSeeder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model {
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'membership_type',
        'package_id',
        'subscription_date',
        'valid_to',
        't_email_send_at',
        's_email_send_at',
        'vsla_enabled',
        'api_enabled',
        'qr_code_enabled',
        'advanced_loan_management_enabled',
    ];

    protected static function boot() {
        parent::boot();

        static::created(function ($tenant) {
            $seeder = new SaasSeeder();
            $seeder->run($tenant->id);
        });
    }

    public function scopeActive($query) {
        return $query->where('status', 1);
    }

    public function package() {
        return $this->belongsTo(Package::class)->withDefault();
    }

    public function subscriptionPayments() {
        return $this->hasMany(SubscriptionPayment::class, 'tenant_id');
    }

    public function owner() {
        return $this->hasOne(User::class)->where('tenant_owner', 1);
    }

    protected function subscriptionDate(): Attribute {
        $date_format = get_date_format();

        return Attribute::make(
            get: fn($value) => \Carbon\Carbon::parse($value)->format("$date_format"),
        );
    }

    protected function validTo(): Attribute {
        $date_format = get_date_format();

        return Attribute::make(
            get: fn($value) => \Carbon\Carbon::parse($value)->format("$date_format"),
        );
    }

    protected function createdAt(): Attribute {
        $date_format = get_date_format();
        $time_format = get_time_format();

        return Attribute::make(
            get: fn($value) => \Carbon\Carbon::parse($value)->format("$date_format $time_format"),
        );
    }

    public function qrCodeSettings()
    {
        return $this->hasOne(QrCodeSetting::class);
    }

    public function isQrCodeEnabled()
    {
        return $this->qrCodeSettings && $this->qrCodeSettings->enabled;
    }

    public function vslaSettings()
    {
        return $this->hasOne(VslaSetting::class);
    }

    public function vslaMeetings()
    {
        return $this->hasMany(VslaMeeting::class);
    }

    public function vslaTransactions()
    {
        return $this->hasMany(VslaTransaction::class);
    }

    public function isVslaEnabled()
    {
        return $this->vsla_enabled;
    }

    public function isApiEnabled()
    {
        return $this->api_enabled ?? false;
    }

}
