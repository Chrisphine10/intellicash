<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class Role extends Model {
    use MultiTenant;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'roles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'tenant_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    public function permissions() {
        return $this->hasMany(AccessControl::class, 'role_id');
    }
}