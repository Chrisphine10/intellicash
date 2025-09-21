<?php

namespace App\Models;

use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

class Page extends Model {

    use Translatable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pages';

    public function scopeActive($query) {
        return $query->where('status', 1);
    }

    public function setSlugAttribute($value) {
        $this->attributes['slug'] = $this->generateSlug($value);
    }

    private function generateSlug($value) {
        if(in_array(strtolower($value), ['home','about','features','pricing','blogs','faq','contact'])){
            return $value.rand();
        }
        $slug   = mb_strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $value)));
        $count  = Page::where('slug', 'LIKE', $slug . '%')->count();
        $suffix = $count > 0 ? $count + 1 : "";
        $slug .= $suffix;
        return $slug;
    }

    public function getCreatedAtAttribute($value) {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

}