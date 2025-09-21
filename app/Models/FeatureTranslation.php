<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureTranslation extends Model {

    protected $fillable = ['title', 'content'];
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'feature_translations';
}