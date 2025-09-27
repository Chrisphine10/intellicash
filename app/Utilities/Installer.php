<?php
namespace App\Utilities;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use \Carbon\Carbon;

/**
 * Class Installer
 *
 * Contains all of the Business logic to install the app. Either through the CLI or the `/install` web UI.
 *
 * @package App\Utilities
 */
class Installer {
    private static $minPhpVersion = '8.2';

    public static function checkServerRequirements() {
        $requirements = [];

        if (phpversion() < self::$minPhpVersion) {
            $requirements[] = 'Minimum PHP Version 8.2 required';
        }

        if (ini_get('safe_mode')) {
            $requirements[] = 'Safe Mode feature needs to be disabled!';
        }

        if (ini_get('register_globals')) {
            $requirements[] = 'Register Globals feature needs to be disabled!';
        }

        if (ini_get('magic_quotes_gpc')) {
            $requirements[] = 'Magic Quotes feature needs to be disabled!';
        }

        if (! ini_get('file_uploads')) {
            $requirements[] = 'File Uploads feature needs to be enabled!';
        }

        if (! class_exists('PDO')) {
            $requirements[] = 'MySQL PDO feature needs to be enabled!';
        }

        if (! extension_loaded('openssl')) {
            $requirements[] = 'OpenSSL extension needs to be loaded!';
        }

        if (! extension_loaded('tokenizer')) {
            $requirements[] = 'Tokenizer extension needs to be loaded!';
        }

        if (! extension_loaded('mbstring')) {
            $requirements[] = 'mbstring extension needs to be loaded!';
        }

        if (! extension_loaded('curl')) {
            $requirements[] = 'cURL extension needs to be loaded!';
        }

        if (! extension_loaded('xml')) {
            $requirements[] = 'XML extension needs to be loaded!';
        }

        if (! extension_loaded('zip')) {
            $requirements[] = 'ZIP extension needs to be loaded!';
        }

        if (! extension_loaded('fileinfo')) {
            $requirements[] = 'fileinfo extension needs to be loaded!';
        }

        if (! is_writable(base_path('storage/app'))) {
            $requirements[] = 'storage/app directory needs to be writable!';
        }

        if (! is_writable(base_path('storage/framework'))) {
            $requirements[] = 'storage/framework directory needs to be writable!';
        }

        if (! is_writable(base_path('storage/logs'))) {
            $requirements[] = 'storage/logs directory needs to be writable!';
        }

        if (! is_writable(base_path('resources/language'))) {
            $requirements[] = 'resources/language directory needs to be writable!';
        }

        if (! is_writable(base_path('public/uploads'))) {
            $requirements[] = 'public/uploads directory needs to be writable!';
        }

        if (! is_writable(base_path('.env'))) {
            $requirements[] = '.env file needs to be writable!';
        }

        return $requirements;
    }

    public static function createDbTables($host, $database, $username, $password) {
        if (! static::isDbValid($host, $database, $username, $password)) {
            return false;
        }

        // Set database details
        static::saveDbVariables($host, 3306, $database, $username, $password);

                              // Try to increase the maximum execution time
        @set_time_limit(300); // 5 minutes

        // Create tables
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
        } catch (\Exception $e) {
            // If migrate:fresh fails due to foreign key constraints, try alternative approach
            Artisan::call('migrate:reset', ['--force' => true]);
            Artisan::call('migrate', ['--force' => true]);
        }

        // Run seeder
        Artisan::call('db:seed', ['--force' => true]);

        return true;
    }

    /**
     * Check if the database exists and is accessible.
     *
     * @param $host
     * @param $port
     * @param $database
     * @param $host
     * @param $database
     * @param $username
     * @param $password
     *
     * @return bool
     */
    public static function isDbValid($host, $database, $username, $password) {
        Config::set('database.connections.install_test', [
            'host'     => $host,
            'port'     => env('DB_PORT', '3306'),
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'driver'   => env('DB_CONNECTION', 'mysql'),
            'charset'  => env('DB_CHARSET', 'utf8mb4'),
        ]);

        try {
            DB::connection('install_test')->getPdo();
        } catch (\Exception $e) {
            ;
            return false;
        }

        // Purge test connection
        DB::purge('install_test');

        return true;
    }

    public static function saveDbVariables($host, $port, $database, $username, $password) {

        // Update .env file
        static::updateEnv([
            'DB_HOST'     => $host,
            'DB_PORT'     => $port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => "'" . $password . "'",
            //'DB_PREFIX'     =>  $prefix,
        ]);

        $con = env('DB_CONNECTION', 'mysql');

        // Change current connection
        $db = Config::get('database.connections.' . $con);

        $db['host']     = $host;
        $db['database'] = $database;
        $db['username'] = $username;
        $db['password'] = $password;
        //$db['prefix'] = $prefix;

        Config::set('database.connections.' . $con, $db);

        DB::purge($con);
        DB::reconnect($con);
    }

    public static function updateSettings($post) {
        try {
            foreach ($post as $key => $value) {
                if ($key == "_token") {
                    continue;
                }

                $data               = [];
                $data['value']      = $value;
                $data['updated_at'] = Carbon::now();
                if (Setting::where('name', $key)->exists()) {
                    Setting::where('name', '=', $key)->update($data);
                } else {
                    $data['name']       = $key;
                    $data['created_at'] = Carbon::now();
                    Setting::insert($data);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to update settings: ' . $e->getMessage());
            throw $e; // Re-throw to handle in controller
        }
    }

    public static function createUser($name, $email, $password) {
        try {
            // Create the user
            $user                    = new User();
            $user->name              = $name;
            $user->email             = $email;
            $user->email_verified_at = now();
            $user->password          = $password;
            $user->status            = 1;
            $user->profile_picture   = 'default.png';
            $user->user_type         = 'superadmin';
            $user->save();
        } catch (\Exception $e) {
            \Log::error('Failed to create user: ' . $e->getMessage());
            throw $e; // Re-throw to handle in controller
        }
    }

    public static function finalTouches($app_name = 'TrickBiz') {
        try {
            // Update .env file
            static::updateEnv([
                'APP_NAME'      => '"' . $app_name . '"',
                'APP_LOCALE'    => session('locale'),
                'APP_INSTALLED' => 'true',
                'APP_DEBUG'     => 'false',
                'APP_URL'       => url(''),
            ]);

            //Import Dummy Data
            $dummyDataPath = public_path('uploads/dummy_data.sql');
            if (file_exists($dummyDataPath)) {
                try {
                    $sql = file_get_contents($dummyDataPath);
                    if ($sql !== false && !empty(trim($sql))) {
                        DB::unprepared($sql);
                    }
                } catch (\Exception $e) {
                    // Log the error but don't fail the installation
                    \Log::error('Failed to import dummy data: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed in finalTouches: ' . $e->getMessage());
            throw $e; // Re-throw to handle in controller
        }
    }

    public static function updateEnv($data) {
        if (empty($data) || ! is_array($data) || ! is_file(base_path('.env'))) {
            return false;
        }

        $env = file_get_contents(base_path('.env'));

        $env = explode("\n", $env);

        foreach ($data as $data_key => $data_value) {
            foreach ($env as $env_key => $env_value) {
                $entry = explode('=', $env_value, 2);

                // Check if new or old key
                if ($entry[0] == $data_key) {
                    $env[$env_key] = $data_key . '=' . $data_value;
                } else {
                    $env[$env_key] = $env_value;
                }
            }
        }

        $env = implode("\n", $env);

        file_put_contents(base_path('.env'), $env);

        return true;
    }
}
