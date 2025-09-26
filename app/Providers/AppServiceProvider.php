<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\ESignatureDocument;
use App\Policies\ESignatureDocumentPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register E-Signature policies
        Gate::policy(ESignatureDocument::class, ESignatureDocumentPolicy::class);

        // Register throttle middleware
        $this->registerThrottleMiddleware();
    }

    /**
     * Register throttle middleware for rate limiting
     */
    private function registerThrottleMiddleware(): void
    {
        $router = $this->app['router'];
        
        $router->aliasMiddleware('throttle:withdraw', function ($request, $next) {
            return app(\Illuminate\Routing\Middleware\ThrottleRequests::class)->handle($request, $next, 'withdraw');
        });

        $router->aliasMiddleware('throttle:admin-withdraw', function ($request, $next) {
            return app(\Illuminate\Routing\Middleware\ThrottleRequests::class)->handle($request, $next, 'admin-withdraw');
        });
    }
}
