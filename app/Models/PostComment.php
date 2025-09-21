<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class PostComment extends Model {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'post_comments';

    protected $fillable = ['user_id', 'post_id', 'parent_id', 'comment', 'name', 'email', 'status'];

    public function scopeActive($query) {
        return $query->where('status', 1);
    }

    public function posted_by() {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }

}