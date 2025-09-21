<?php
namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait MultiTenant {

    public static function bootMultiTenant() {
        $table = (new self())->getTable();

        if (auth()->check()) {
            $user = auth()->user();

            static::saving(function ($model) use ($user) {
                if (Schema::hasColumn($model->getTable(), 'tenant_id') && $user->user_type != 'superadmin') {
                    $model->tenant_id = $user->tenant_id;
                }

                if (Schema::hasColumn($model->getTable(), 'created_user_id')) {
                    if (! $model->exists) {
                        $model->created_user_id = $user->id;
                    }
                }
                if (Schema::hasColumn($model->getTable(), 'updated_user_id')) {
                    if ($model->exists) {
                        $model->updated_user_id = $user->id;
                    }
                }
            });

            static::updating(function ($model) use ($user) {
                if (Schema::hasColumn($model->getTable(), 'tenant_id') && $user->user_type != 'superadmin') {
                    $model->tenant_id = $user->tenant_id;
                }
                if (Schema::hasColumn($model->getTable(), 'updated_user_id')) {
                    $model->updated_user_id = $user->id;
                }
            });

            static::addGlobalScope('tenant', function (Builder $builder) use ($table, $user) {
                if (Schema::hasColumn($table, 'tenant_id') && $user->user_type != 'superadmin') {
                    return $builder->where($table . '.tenant_id', $user->tenant_id);
                }
            });

        }

    }

}