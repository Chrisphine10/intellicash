<?php
namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Utilities\Installer;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class InstallController extends Controller {
    public function __construct() {
        if (env('APP_INSTALLED', false) == true) {
            Redirect::to('/')->send();
        }
    }

    public function index() {
        $requirements = Installer::checkServerRequirements();
        return view('install.step_1', compact('requirements'));
    }

    public function database() {
        return view('install.step_2');
    }

    public function process_install(Request $request) {
        $host            = $request->hostname;
        $database        = $request->database;
        $username        = $request->username;
        $password        = $request->password;

        if (Installer::createDbTables($host, $database, $username, $password) == false) {
            return redirect()->back()->with("error", "Invalid Database Settings !")->withInput();
        }

        return redirect('install/create_user');
    }

    public function create_user() {
        return view('install.step_3');
    }

    public function store_user(Request $request) {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:191',
            'email'    => 'required|string|email|max:191|unique:users',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $name     = $request->name;
            $email    = $request->email;
            $password = Hash::make($request->password);

            Installer::createUser($name, $email, $password);

            return redirect('install/system_settings');
        } catch (\Exception $e) {
            \Log::error('User creation failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to create user. Please try again.')
                ->withInput();
        }
    }

    public function system_settings() {
        return view('install.step_4');
    }

    public function final_touch(Request $request) {
        try {
            Installer::updateSettings($request->all());
            Installer::finalTouches($request->site_title);
            return redirect()->route('admin.settings.update_settings');
        } catch (\Exception $e) {
            \Log::error('Final touch failed: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Installation failed. Please check the logs and try again.')
                ->withInput();
        }
    }

}
