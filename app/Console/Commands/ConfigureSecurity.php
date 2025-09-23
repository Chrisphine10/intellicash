<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConfigureSecurity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:configure {--force : Force configuration even if already set}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure security settings for the application';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔒 Configuring IntelliCash Security Settings...');
        
        $this->configureSessionSecurity();
        $this->configureCsrfProtection();
        $this->configureSecurityHeaders();
        $this->configurePasswordSecurity();
        $this->configureFileUploadSecurity();
        $this->configureAuditLogging();
        
        $this->info('✅ Security configuration completed successfully!');
        $this->info('🌐 Access security testing at: http://localhost/intellicash/admin/security/testing');
    }

    /**
     * Configure session security settings
     */
    private function configureSessionSecurity()
    {
        $this->info('📝 Configuring session security...');
        
        $envFile = base_path('.env');
        $envContent = File::exists($envFile) ? File::get($envFile) : '';
        
        $sessionSettings = [
            'SESSION_ENCRYPT=true',
            'SESSION_SECURE_COOKIE=true',
            'SESSION_HTTP_ONLY=true',
            'SESSION_SAME_SITE=strict',
            'SESSION_LIFETIME=120',
            'SESSION_EXPIRE_ON_CLOSE=true',
        ];
        
        foreach ($sessionSettings as $setting) {
            $key = explode('=', $setting)[0];
            if (!str_contains($envContent, $key) || $this->option('force')) {
                $envContent = $this->updateEnvSetting($envContent, $key, explode('=', $setting)[1]);
            }
        }
        
        File::put($envFile, $envContent);
        $this->line('   ✅ Session security configured');
    }

    /**
     * Configure CSRF protection
     */
    private function configureCsrfProtection()
    {
        $this->info('🛡️ Configuring CSRF protection...');
        
        $envFile = base_path('.env');
        $envContent = File::exists($envFile) ? File::get($envFile) : '';
        
        $csrfSettings = [
            'CSRF_ENABLED=true',
        ];
        
        foreach ($csrfSettings as $setting) {
            $key = explode('=', $setting)[0];
            if (!str_contains($envContent, $key) || $this->option('force')) {
                $envContent = $this->updateEnvSetting($envContent, $key, explode('=', $setting)[1]);
            }
        }
        
        File::put($envFile, $envContent);
        $this->line('   ✅ CSRF protection configured');
    }

    /**
     * Configure security headers
     */
    private function configureSecurityHeaders()
    {
        $this->info('🔐 Configuring security headers...');
        
        $envFile = base_path('.env');
        $envContent = File::exists($envFile) ? File::get($envFile) : '';
        
        $headerSettings = [
            'X_FRAME_OPTIONS=DENY',
            'X_CONTENT_TYPE_OPTIONS=nosniff',
            'X_XSS_PROTECTION=1; mode=block',
            'REFERRER_POLICY=strict-origin-when-cross-origin',
            'CONTENT_SECURITY_POLICY=default-src \'self\'',
        ];
        
        foreach ($headerSettings as $setting) {
            $key = explode('=', $setting)[0];
            if (!str_contains($envContent, $key) || $this->option('force')) {
                $envContent = $this->updateEnvSetting($envContent, $key, explode('=', $setting)[1]);
            }
        }
        
        File::put($envFile, $envContent);
        $this->line('   ✅ Security headers configured');
    }

    /**
     * Configure password security
     */
    private function configurePasswordSecurity()
    {
        $this->info('🔑 Configuring password security...');
        
        $envFile = base_path('.env');
        $envContent = File::exists($envFile) ? File::get($envFile) : '';
        
        $passwordSettings = [
            'PASSWORD_MIN_LENGTH=8',
            'PASSWORD_REQUIRE_UPPERCASE=true',
            'PASSWORD_REQUIRE_LOWERCASE=true',
            'PASSWORD_REQUIRE_NUMBERS=true',
            'PASSWORD_REQUIRE_SYMBOLS=true',
            'PASSWORD_MAX_AGE_DAYS=90',
        ];
        
        foreach ($passwordSettings as $setting) {
            $key = explode('=', $setting)[0];
            if (!str_contains($envContent, $key) || $this->option('force')) {
                $envContent = $this->updateEnvSetting($envContent, $key, explode('=', $setting)[1]);
            }
        }
        
        File::put($envFile, $envContent);
        $this->line('   ✅ Password security configured');
    }

    /**
     * Configure file upload security
     */
    private function configureFileUploadSecurity()
    {
        $this->info('📁 Configuring file upload security...');
        
        $envFile = base_path('.env');
        $envContent = File::exists($envFile) ? File::get($envFile) : '';
        
        $uploadSettings = [
            'FILE_UPLOAD_MAX_SIZE=10240',
            'FILE_UPLOAD_SCAN=true',
            'FILE_UPLOAD_QUARANTINE=true',
        ];
        
        foreach ($uploadSettings as $setting) {
            $key = explode('=', $setting)[0];
            if (!str_contains($envContent, $key) || $this->option('force')) {
                $envContent = $this->updateEnvSetting($envContent, $key, explode('=', $setting)[1]);
            }
        }
        
        File::put($envFile, $envContent);
        $this->line('   ✅ File upload security configured');
    }

    /**
     * Configure audit logging
     */
    private function configureAuditLogging()
    {
        $this->info('📊 Configuring audit logging...');
        
        $envFile = base_path('.env');
        $envContent = File::exists($envFile) ? File::get($envFile) : '';
        
        $auditSettings = [
            'AUDIT_ENABLED=true',
            'AUDIT_LOG_LEVEL=info',
            'AUDIT_RETENTION_DAYS=365',
            'AUDIT_LOG_FAILED_ATTEMPTS=true',
            'AUDIT_LOG_SUCCESSFUL_LOGINS=true',
            'AUDIT_LOG_ADMIN_ACTIONS=true',
        ];
        
        foreach ($auditSettings as $setting) {
            $key = explode('=', $setting)[0];
            if (!str_contains($envContent, $key) || $this->option('force')) {
                $envContent = $this->updateEnvSetting($envContent, $key, explode('=', $setting)[1]);
            }
        }
        
        File::put($envFile, $envContent);
        $this->line('   ✅ Audit logging configured');
    }

    /**
     * Update or add an environment setting
     */
    private function updateEnvSetting(string $envContent, string $key, string $value): string
    {
        $pattern = "/^{$key}=.*$/m";
        $replacement = "{$key}={$value}";
        
        if (preg_match($pattern, $envContent)) {
            return preg_replace($pattern, $replacement, $envContent);
        } else {
            return $envContent . "\n{$replacement}";
        }
    }
}
