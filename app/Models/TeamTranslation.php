<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamTranslation extends Model {

    protected $fillable = ['name', 'role', 'description'];
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'team_translations';
}