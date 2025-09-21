<?php

namespace App\Models;

use App\Traits\Member;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class MemberDocument extends Model {

    use MultiTenant, Member;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'member_documents';

    public function member() {
        return $this->belongsTo('App\Models\Member', 'member_id')->withDefault();
    }
}