<?php
namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Utilities\Installer;
use Illuminate\Support\Facades\Artisan;
use ZipArchive;

class UpdateController extends Controller {
    private $updateFileName = 'credit-lite-saas-update.zip';
    private $app_version    = '1.3';

    public function index($action = '') {
        if (! file_exists($this->updateFileName)) {
            return redirect('/');
        }

        if ($action == 'process') {
            Installer::updateEnv(['APP_DEBUG' => 'true']);

            $zip = new ZipArchive();
            $zip->open($this->updateFileName, ZipArchive::CREATE);
            $zip->deleteName('.env');
            $zip->close();

            $zip->open($this->updateFileName, ZipArchive::CREATE);
            $zip->extractTo(".");
            $zip->close();

            unlink($this->updateFileName);

            $this->updateMigration();

            Installer::updateEnv(['APP_DEBUG' => 'false']);

            return redirect('migration/update');
        }

        $requirements = Installer::checkServerRequirements();
        return view('install.update', compact('requirements'));
    }

    public function update_migration() {
        $this->updateMigration();
        return redirect()->route('admin.login')->with('success', 'System has been updated to version ' . $this->app_version);
    }

    private function updateMigration() {
        Artisan::call('migrate', ['--force' => true]);

        //Update Version Number to env file
        Installer::updateEnv([
            'APP_VERSION' => $this->app_version,
        ]);

        //Update Version Number to database
        update_option('APP_VERSION', $this->app_version);
    }
}
