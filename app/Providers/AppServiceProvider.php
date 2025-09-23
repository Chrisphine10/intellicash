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
    }
}
