<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model {
    use MultiTenant;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'branches';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];

    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
        $this->attributes['name'] = get_tenant_option('default_branch_name', 'Main Branch');
    }
    

}