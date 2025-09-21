<?php

namespace App\Providers;

use App\Services\ReceiptQrService;
use App\Services\EthereumService;
use App\Services\CryptographicProtectionService;
use Illuminate\Support\ServiceProvider;

class ReceiptQrServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CryptographicProtectionService::class);
        $this->app->singleton(EthereumService::class);
        $this->app->singleton(ReceiptQrService::class, function ($app) {
            return new ReceiptQrService(
                $app->make(CryptographicProtectionService::class),
                $app->make(EthereumService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
