<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'packages';

    public function scopeActive($query) {
        return $query->where('status', 1);
    }
}