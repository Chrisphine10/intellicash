<?php
namespace App\Utilities;

class Overrider {

    public static function load($type) {
        $method = 'load' . ucfirst($type);
        static::$method();
    }

    protected static function loadSettings() {
        // Timezone
        config(['app.timezone' => get_timezone()]);

        if (app()->bound('tenant')) {
            $email_protocol = get_tenant_option('mail_type', '', app('tenant')->id);

            if ($email_protocol != '') {
                self::loadTenantSettings();
                return;
            }
        }

        $email_protocol = get_option('mail_type', 'sendmail');
        config(['mail.default' => $email_protocol]);

        config(['mail.from.name' => get_option('from_name')]);
        config(['mail.from.address' => get_option('from_email')]);

        if ($email_protocol == 'smtp') {
            config(['mail.mailers.smtp.host' => get_option('smtp_host')]);
            config(['mail.mailers.smtp.port' => get_option('smtp_port')]);
            config(['mail.mailers.smtp.username' => get_option('smtp_username')]);
            config(['mail.mailers.smtp.password' => get_option('smtp_password')]);
            config(['mail.mailers.smtp.encryption' => get_option('smtp_encryption')]);
        }

    }

    public static function loadTenantSettings($tenantId = '') {
        $email_protocol = get_tenant_option('mail_type', '', $tenantId);

        config(['mail.default' => $email_protocol]);

        config(['mail.from.name' => get_tenant_option('from_name', '', $tenantId)]);
        config(['mail.from.address' => get_tenant_option('from_email', '', $tenantId)]);

        if ($email_protocol == 'smtp') {
            config(['mail.mailers.smtp.host' => get_tenant_option('smtp_host', '', $tenantId)]);
            config(['mail.mailers.smtp.port' => get_tenant_option('smtp_port', '', $tenantId)]);
            config(['mail.mailers.smtp.username' => get_tenant_option('smtp_username', '', $tenantId)]);
            config(['mail.mailers.smtp.password' => get_tenant_option('smtp_password', '', $tenantId)]);
            config(['mail.mailers.smtp.encryption' => get_tenant_option('smtp_encryption', '', $tenantId)]);
        }
    }

    protected static function loadSocialSettings() {
        //Set Google Login Credentials
        config(['services.google' => [
            'client_id'     => get_option('GOOGLE_CLIENT_ID'),
            'client_secret' => get_option('GOOGLE_CLIENT_SECRET'),
            'redirect'      => url('login/google/callback'),
        ],
        ]);

        //Set Facebook Login Credentials
        config(['services.facebook' => [
            'client_id'     => get_option('FACEBOOK_CLIENT_ID'),
            'client_secret' => get_option('FACEBOOK_CLIENT_SECRET'),
            'redirect'      => url('login/facebook/callback'),
        ],
        ]);
    }

}