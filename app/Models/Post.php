<?php

namespace App\Models;

use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Post extends Model {
    use Translatable;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'posts';

    public function scopeActive($query) {
        return $query->where('status', 1);
    }

    public function setSlugAttribute($value) {
        $this->attributes['slug'] = $this->generateSlug($value);
    }

    public function author() {
        return $this->belongsTo(User::class, 'created_user_id')->withDefault();
    }

    public function comments() {
        return $this->hasMany(PostComment::class, 'post_id');
    }

    private function generateSlug($value) {
        $slug   = mb_strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $value)));
        $count  = Post::where('slug', 'LIKE', $slug . '%')->count();
        $suffix = $count > 0 ? $count + 1 : "";
        $slug .= $suffix;
        return $slug;
    }

    protected function createdAt(): Attribute {
        $date_format = get_date_format();
        $time_format = get_time_format();

        return Attribute::make(
            get:fn($value) => \Carbon\Carbon::parse($value)->format("$date_format $time_format"),
        );
    }

}