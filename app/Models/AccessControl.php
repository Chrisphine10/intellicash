<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessControl extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'permissions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'role_id',
        'permission',
    ];

    /**
     * Get the role that owns the permission.
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}