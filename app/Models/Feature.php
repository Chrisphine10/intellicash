<?php

namespace App\Models;

use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model {
    use Translatable;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'features';

    public function scopeActive($query) {
        return $query->where('status', 1);
    }
}