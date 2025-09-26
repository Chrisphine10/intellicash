<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\Mailer\Exception\TransportException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth'            => \App\Http\Middleware\Authenticate::class,
            'guest'           => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'install'         => \App\Http\Middleware\CanInstall::class,
            'superadmin'      => \App\Http\Middleware\EnsureSuperAdmin::class,
            'tenant'          => \App\Http\Middleware\IdentifyTenant::class,
            'tenant.global'   => \App\Http\Middleware\EnsureGlobalTenantUser::class,
            'tenant.admin'    => \App\Http\Middleware\EnsureTenantAdmin::class,
            'tenant.user'     => \App\Http\Middleware\EnsureTenantUser::class,
            'tenant.customer' => \App\Http\Middleware\EnsureTenantCustomer::class,
            'demo'            => \App\Http\Middleware\Demo::class,
            '2fa'             => \PragmaRX\Google2FALaravel\Middleware::class,
            'vsla.access'     => \App\Http\Middleware\EnsureVslaAccess::class,
            'asset_module'    => \App\Http\Middleware\AssetModuleMiddleware::class,
            'payroll_module'     => \App\Http\Middleware\PayrollModuleMiddleware::class,
            'payroll_rate_limit' => \App\Http\Middleware\PayrollRateLimitMiddleware::class,
            'payroll_csrf'       => \App\Http\Middleware\PayrollCSRFProtection::class,
            'military.security' => \App\Http\Middleware\MilitaryGradeSecurity::class,
            'api.auth'         => \App\Http\Middleware\ApiAuth::class,
            'security.headers' => \App\Http\Middleware\EnforceSecurityHeaders::class,
            'esignature.access' => \App\Http\Middleware\ESignatureAccess::class,
            'esignature.rate.limit' => \App\Http\Middleware\ESignatureRateLimit::class,
            // SECURITY: New tenant isolation middleware
            'tenant.isolation' => \App\Http\Middleware\EnsureTenantIsolation::class,
            'prevent.global.scope.bypass' => \App\Http\Middleware\PreventGlobalScopeBypass::class,
            'member.access' => \App\Http\Middleware\MemberAccessControl::class,
            'rate.limit' => \App\Http\Middleware\RateLimitSecurity::class,
            'csrf.enhanced' => \App\Http\Middleware\EnhancedCsrfProtection::class,
            'transaction.auth' => \App\Http\Middleware\TransactionAuthorization::class,
            'admin.access' => \App\Http\Middleware\EnsureAdminAccess::class,
            'report.rate.limit' => \App\Http\Middleware\ReportRateLimit::class,
            'tenant.access' => \App\Http\Middleware\TenantAccess::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'sms.security' => \App\Http\Middleware\SMSSecurityMiddleware::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            '*/callback/instamojo',
            'subscription_callback/instamojo',
        ]);
        
        // Apply military-grade security globally (excluding API routes)
        $middleware->web(append: [
            \App\Http\Middleware\MilitaryGradeSecurity::class,
            \App\Http\Middleware\EnforceSecurityHeaders::class,
        ]);
        
        // Apply API middleware to API routes
        $middleware->api(append: [
            \App\Http\Middleware\MilitaryGradeSecurity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (TransportException $e) {
            if (request()->ajax()) {
                return response()->json(['result' => 'error', 'message' => 'SMTP Configuration is incorrect !']);
            } else {
                return redirect()->route('login')->with('error', 'SMTP Configuration is incorrect !');
            }
        });

        $exceptions->render(function (TokenMismatchException $e) {
            if (request()->ajax()) {
                return response()->json(['result' => 'error', 'message' => 'Your session has expired, please try again !']);
            } else {
                return redirect()->back()->with('error', 'Your session has expired, please try again !');
            }
        });

        $exceptions->render(function (PostTooLargeException $e) {
            $sizeUploadMax = ini_get("upload_max_filesize");
            return back()->with('error', "You cannot upload more than $sizeUploadMax each file !");
        });
    })->create();
