<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TransactionCategory extends Model
{
    use MultiTenant;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction_categories';

     /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted() {
        static::addGlobalScope('status', function (Builder $builder) {
            return $builder->where('status', 1);
        });
    }
}