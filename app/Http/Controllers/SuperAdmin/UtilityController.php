<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\GeneralMail;
use App\Models\Setting;
use App\Models\SettingTranslation;
use App\Utilities\Overrider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class UtilityController extends Controller {
    /**
     * Show the Settings Page.
     *
     * @return Response
     */

    public function __construct() {
        header('Cache-Control: no-cache');
        header('Pragma: no-cache');
        date_default_timezone_set(get_timezone());
    }

    public function settings(Request $request, $store = null) {
        if (is_null($store)) {
            return view('backend.super_admin.administration.general_settings.settings');
        }

        // Process input values
        $inputs = $request->except(array_merge(['_token'], array_keys($request->allFiles())));
        foreach ($inputs as $key => $value) {
            if($value == null){
                continue;
            }
            $data = [
                'value'      => $value,
                'updated_at' => Carbon::now(),
            ];

            Setting::updateOrInsert(['name' => $key], $data);
            Cache::put($key, $value);
        }

        // Process uploaded files
        foreach ($request->allFiles() as $key => $file) {
            $this->upload_file($key, $request);
        }

        // Forget Cached settings
        Cache::forget('currency_position');
        Cache::forget('currency');
        Cache::forget('date_format');
        Cache::forget('time_format');
        Cache::forget('language');
        Cache::forget('timezone');

        if ($request->ajax()) {
            return response()->json([
                'result'  => 'success',
                'action'  => 'update',
                'message' => _lang('Saved successfully'),
            ]);
        }

        return redirect()->route('admin.settings.update_settings')
            ->with('success', _lang('Saved successfully'));
    }

    public function upload_logo(Request $request) {
        $this->validate($request, [
            'logo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $image           = $request->file('logo');
            $name            = 'logo.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('/uploads/media');
            $image->move($destinationPath, $name);

            $data               = [];
            $data['value']      = $name;
            $data['updated_at'] = Carbon::now();

            if (Setting::where('name', "logo")->exists()) {
                Setting::where('name', '=', "logo")->update($data);
            } else {
                $data['name']       = "logo";
                $data['created_at'] = Carbon::now();
                Setting::insert($data);
            }

            Cache::put("logo", $name);

            if ($request->ajax()) {
                return response()->json([
                    'result'  => 'success',
                    'action'  => 'update',
                    'message' => _lang('Logo Upload successfully'),
                ]);
            }

            return redirect()->route('admin.settings.update_settings')
                ->with('success', _lang('Saved successfully'));

        }
    }

    public function upload_file($file_name, Request $request) {
        if ($request->hasFile($file_name)) {
            $file            = $request->file($file_name);
            $name            = 'file_' . rand() . time() . "." . $file->getClientOriginalExtension();
            $destinationPath = public_path('/uploads/media');
            $file->move($destinationPath, $name);

            $data               = [];
            $data['value']      = $name;
            $data['updated_at'] = Carbon::now();

            if (Setting::where('name', $file_name)->exists()) {
                Setting::where('name', '=', $file_name)->update($data);
            } else {
                $data['name']       = $file_name;
                $data['created_at'] = Carbon::now();
                Setting::insert($data);
            }
            Cache::put($file_name, $name);
        }
    }

    public function theme_option(Request $request, $store = null) {
        if (is_null($store)) {
            return view("backend.super_admin.administration.general_settings.theme_option");
        }

        $locale = get_language(); // Get current language

        // Process form inputs
        foreach ($request->except('_token') as $key => $value) {
            if($value == null){
                continue;
            }
            
            $formattedValue = is_array($value) ? json_encode($value) : $value;

            // Retrieve or create setting
            $setting = Setting::updateOrCreate(
                ['name' => $key],
                ['value' => $formattedValue, 'updated_at' => Carbon::now()]
            );

            // Update or create translation
            $translation = SettingTranslation::firstOrNew([
                'setting_id' => $setting->id,
                'locale'     => $locale,
            ]);
            $translation->value = $formattedValue;
            $translation->save();

            // Update cache
            Cache::put($key, $value);
            Cache::put("{$key}-{$locale}", $value);
        }

        // Handle file uploads
        foreach ($request->allFiles() as $key => $file) {
            $this->upload_file($key, $request);
        }

        // Return response
        if ($request->ajax()) {
            return response()->json([
                'result'  => 'success',
                'action'  => 'update',
                'message' => _lang('Saved successfully'),
            ]);
        }

        return back()->with('success', _lang('Saved successfully'));
    }

    public function remove_cache(Request $request) {
        $request->validate([
            'cache' => 'required|array',
        ]);

        if ($request->input('cache.view_cache')) {
            Artisan::call('view:clear');
        }

        if ($request->input('cache.application_cache')) {
            Artisan::call('cache:clear');
        }

        return back()->with('success', __('Cache removed successfully'));
    }

    public function send_test_email(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        Overrider::load("Settings");

        $request->validate([
            'email_address' => 'required|email',
            'message'       => 'required',
        ]);

        // Prepare email data
        $email   = $request->input("email_address");
        $message = $request->input("message");

        $mail = (object) [
            'subject' => "Email Configuration Testing",
            'body'    => $message,
        ];

        try {
            Mail::to($email)->send(new GeneralMail($mail));

            $response = [
                'result'  => 'success',
                'action'  => 'update',
                'message' => __('Your message was sent successfully'),
            ];

            return $request->ajax() ? response()->json($response) : back()->with('success', $response['message']);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            $response = [
                'result'  => 'error',
                'action'  => 'update',
                'message' => $errorMessage,
            ];

            return $request->ajax() ? response()->json($response) : back()->with('error', $errorMessage);
        }
    }

}