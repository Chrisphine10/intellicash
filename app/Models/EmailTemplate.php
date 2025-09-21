<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'email_templates';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'subject',
        'email_body',
        'sms_body',
        'notification_body',
        'shortcode',
        'email_status',
        'sms_status',
        'notification_status',
        'template_mode',
        'template_type'
    ];

    public function tenantTemplate() {
        return $this->hasOne(EmailTemplate::class, 'slug', 'slug')->whereNotNull('tenant_id');
    }
}