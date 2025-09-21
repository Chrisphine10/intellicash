<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\GeneralMail;
use App\Models\TenantSetting;
use App\Utilities\Overrider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class TenantSettingsController extends Controller
{

    private $ignoreRequests = ['_token'];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        date_default_timezone_set(get_timezone());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $alert_col = 'col-lg-10 offset-lg-1';
        $settings  = TenantSetting::all();
        return view('backend.admin.settings.index', compact('settings', 'alert_col'));
    }

    public function store_general_settings(Request $request)
    {
        $settingsData = $request->except($this->ignoreRequests);

        foreach ($settingsData as $key => $value) {
            $value = is_array($value) ? json_encode($value) : $value;
            update_tenant_option($key, $value);
        }

        if ($request->ajax()) {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Saved Successfully')]);
        }
        return back()->with('success', _lang('Saved Successfully'));
    }

    public function store_currency_settings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency_position' => 'required',
            'decimal_places'    => 'required|integer',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        $settingsData = $request->except($this->ignoreRequests);

        foreach ($settingsData as $key => $value) {
            update_tenant_option($key, $value);
        }

        if ($request->ajax()) {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Saved Successfully')]);
        }
        return back()->with('success', _lang('Saved Successfully'));
    }

    public function upload_logo(Request $request)
    {
        $this->validate($request, [
            'logo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $image           = $request->file('logo');
            $name            = 'logo-' . uniqid() . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('/uploads/media');
            $image->move($destinationPath, $name);

            update_tenant_option("logo", $name);

            if ($request->ajax()) {
                return response()->json([
                    'result'  => 'success',
                    'action'  => 'update',
                    'message' => _lang('Logo Upload successfully'),
                ]);
            }

            return back()->with('success', _lang('Saved successfully'));

        }
    }

    public function store_email_settings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_email'      => 'required_if:mail_type,smtp,sendmail',
            'from_name'       => 'required_if:mail_type,smtp,sendmail',
            'smtp_host'       => 'required_if:mail_type,smtp,sendmail',
            'smtp_port'       => 'required_if:mail_type,smtp,sendmail',
            'smtp_username'   => 'required_if:mail_type,smtp,sendmail',
            'smtp_password'   => 'required_if:mail_type,smtp,sendmail',
            'smtp_encryption' => 'required_if:mail_type,smtp,sendmail',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        $settingsData = $request->except($this->ignoreRequests);

        foreach ($settingsData as $key => $value) {
            update_tenant_option($key, $value);
        }

        if ($request->ajax()) {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Saved Successfully')]);
        }
        return back()->with('success', _lang('Saved Successfully'));
    }

    public function send_test_email(Request $request)
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        Overrider::load("TenantSettings");

        $this->validate($request, [
            'recipient_email' => 'required|email',
            'message'         => 'required',
        ]);

        //Send Email
        $email   = $request->input("recipient_email");
        $message = $request->input("message");

        $mail          = new \stdClass();
        $mail->subject = "Email Configuration Testing";
        $mail->body    = $message;

        try {
            Mail::to($email)->send(new GeneralMail($mail));
            if ($request->ajax()) {
                return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Test Message send sucessfully')]);
            }
            return back()->with('success', _lang('Test Message send sucessfully'));
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'action' => 'update', 'message' => $e->getMessage()]);
            }
            return back()->with('error', $e->getMessage());
        }
    }

}
