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
     * Whitelist of allowed settings for general settings
     */
    private $allowedGeneralSettings = [
        'business_name',
        'email', 
        'phone',
        'timezone',
        'language',
        'address',
        'starting_member_no',
        'members_sign_up',
        'default_branch_name',
        'backend_direction',
        'date_format',
        'time_format',
        'own_account_transfer_fee_type',
        'own_account_transfer_fee',
        'other_account_transfer_fee_type',
        'other_account_transfer_fee',
        'pwa_enabled',
        'pwa_app_name',
        'pwa_short_name',
        'pwa_description',
        'pwa_theme_color',
        'pwa_background_color',
        'pwa_shortcut_dashboard',
        'pwa_shortcut_transactions',
        'pwa_shortcut_loans',
        'pwa_shortcut_deposit',
        'pwa_shortcut_profile',
        'pwa_display_mode',
        'pwa_orientation',
        'pwa_offline_support',
        'pwa_cache_strategy'
    ];
    
    /**
     * Whitelist of allowed settings for currency settings
     */
    private $allowedCurrencySettings = [
        'currency_position',
        'thousand_sep',
        'decimal_sep',
        'decimal_places'
    ];
    
    /**
     * Whitelist of allowed settings for email settings
     */
    private $allowedEmailSettings = [
        'mail_type',
        'from_email',
        'from_name',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption'
    ];
    
    /**
     * Whitelist of allowed settings for SMS settings
     */
    private $allowedSmsSettings = [
        'sms_gateway',
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_number',
        'textmagic_username',
        'textmagic_api_key',
        'nexmo_api_key',
        'nexmo_api_secret',
        'infobip_api_key',
        'infobip_api_base_url',
        'africas_talking_username',
        'africas_talking_api_key',
        'africas_talking_sender_id'
    ];

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
        // Validate input data
        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'timezone' => 'required|string|max:50',
            'language' => 'required|string|max:10',
            'address' => 'nullable|string|max:500',
            'starting_member_no' => 'nullable|integer|min:1',
            'members_sign_up' => 'required|boolean',
            'default_branch_name' => 'nullable|string|max:255',
            'backend_direction' => 'required|in:ltr,rtl',
            'date_format' => 'required|string|max:20',
            'time_format' => 'required|in:12,24',
            'own_account_transfer_fee_type' => 'nullable|in:percentage,fixed',
            'own_account_transfer_fee' => 'nullable|numeric|min:0|max:999999',
            'other_account_transfer_fee_type' => 'nullable|in:percentage,fixed',
            'other_account_transfer_fee' => 'nullable|numeric|min:0|max:999999',
            'pwa_enabled' => 'nullable|boolean',
            'pwa_app_name' => 'nullable|string|max:100',
            'pwa_short_name' => 'nullable|string|max:50',
            'pwa_description' => 'nullable|string|max:500',
            'pwa_theme_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'pwa_background_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'pwa_shortcut_dashboard' => 'nullable|boolean',
            'pwa_shortcut_transactions' => 'nullable|boolean',
            'pwa_shortcut_loans' => 'nullable|boolean',
            'pwa_shortcut_deposit' => 'nullable|boolean',
            'pwa_shortcut_profile' => 'nullable|boolean',
            'pwa_display_mode' => 'nullable|in:standalone,fullscreen,minimal-ui,browser',
            'pwa_orientation' => 'nullable|in:portrait-primary,landscape-primary,any',
            'pwa_offline_support' => 'nullable|boolean',
            'pwa_cache_strategy' => 'nullable|in:cache-first,network-first,stale-while-revalidate'
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        // Only allow whitelisted settings
        $settingsData = $request->only($this->allowedGeneralSettings);

        foreach ($settingsData as $key => $value) {
            // Handle masked passwords - don't update if value is masked
            if ($this->isMaskedValue($key, $value)) {
                continue;
            }
            
            // Sanitize the value
            $value = $this->sanitizeSettingValue($key, $value);
            update_tenant_option($key, $value);
        }

        // Log the settings change
        $this->logSettingsChange('general_settings', $settingsData, $request);

        if ($request->ajax()) {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Saved Successfully')]);
        }
        return back()->with('success', _lang('Saved Successfully'));
    }

    public function store_currency_settings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency_position' => 'required|in:left,right',
            'decimal_places'    => 'required|integer|min:0|max:10',
            'thousand_sep' => 'nullable|string|max:5',
            'decimal_sep' => 'nullable|string|max:5'
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        // Only allow whitelisted settings
        $settingsData = $request->only($this->allowedCurrencySettings);

        foreach ($settingsData as $key => $value) {
            // Handle masked passwords - don't update if value is masked
            if ($this->isMaskedValue($key, $value)) {
                continue;
            }
            
            // Sanitize the value
            $value = $this->sanitizeSettingValue($key, $value);
            update_tenant_option($key, $value);
        }

        // Log the settings change
        $this->logSettingsChange('currency_settings', $settingsData, $request);

        if ($request->ajax()) {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Saved Successfully')]);
        }
        return back()->with('success', _lang('Saved Successfully'));
    }

    public function upload_logo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,png,jpg|max:2048|dimensions:max_width=1000,max_height=1000'
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        if ($request->hasFile('logo')) {
            $image = $request->file('logo');
            
            // Additional security checks
            $imageInfo = getimagesize($image->getRealPath());
            if (!$imageInfo) {
                $errorMessage = 'Invalid image file';
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => $errorMessage]);
                }
                return back()->with('error', $errorMessage);
            }
            
            // Generate secure filename
            $hash = hash_file('sha256', $image->getRealPath());
            $extension = $image->getClientOriginalExtension();
            $name = 'logo-' . substr($hash, 0, 16) . '.' . $extension;
            
            $destinationPath = public_path('/uploads/media');
            
            // Ensure directory exists and is writable
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            
            // Move file with error handling
            if ($image->move($destinationPath, $name)) {
                update_tenant_option("logo", $name);
                
                // Log the file upload
                $this->logSettingsChange('logo_upload', ['logo' => $name], $request);

                if ($request->ajax()) {
                    return response()->json([
                        'result'  => 'success',
                        'action'  => 'update',
                        'message' => _lang('Logo Upload successfully'),
                    ]);
                }

                return back()->with('success', _lang('Saved successfully'));
            } else {
                $errorMessage = 'Failed to upload file';
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => $errorMessage]);
                }
                return back()->with('error', $errorMessage);
            }
        }
        
        $errorMessage = 'No file uploaded';
        if ($request->ajax()) {
            return response()->json(['result' => 'error', 'message' => $errorMessage]);
        }
        return back()->with('error', $errorMessage);
    }

    public function store_email_settings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mail_type' => 'required|in:smtp,sendmail',
            'from_email'      => 'required_if:mail_type,smtp,sendmail|email|max:255',
            'from_name'       => 'required_if:mail_type,smtp,sendmail|string|max:255',
            'smtp_host'       => 'required_if:mail_type,smtp|string|max:255',
            'smtp_port'       => 'required_if:mail_type,smtp|integer|min:1|max:65535',
            'smtp_username'   => 'required_if:mail_type,smtp|string|max:255',
            'smtp_password'   => 'required_if:mail_type,smtp|string|max:255',
            'smtp_encryption' => 'required_if:mail_type,smtp|in:ssl,tls',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        // Only allow whitelisted settings
        $settingsData = $request->only($this->allowedEmailSettings);

        foreach ($settingsData as $key => $value) {
            // Handle masked passwords - don't update if value is masked
            if ($this->isMaskedValue($key, $value)) {
                continue;
            }
            
            // Sanitize the value
            $value = $this->sanitizeSettingValue($key, $value);
            update_tenant_option($key, $value);
        }

        // Log the settings change
        $this->logSettingsChange('email_settings', $settingsData, $request);

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

    /**
     * Sanitize setting value based on setting type
     */
    private function sanitizeSettingValue($key, $value)
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        
        // Sanitize based on setting type
        switch ($key) {
            case 'email':
            case 'from_email':
                return filter_var($value, FILTER_SANITIZE_EMAIL);
            
            case 'phone':
                return preg_replace('/[^0-9+\-\s\(\)]/', '', $value);
            
            case 'business_name':
            case 'from_name':
            case 'default_branch_name':
            case 'pwa_app_name':
            case 'pwa_short_name':
                return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
            
            case 'address':
            case 'pwa_description':
                return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
            
            case 'smtp_password':
            case 'twilio_auth_token':
            case 'textmagic_api_key':
            case 'nexmo_api_secret':
            case 'infobip_api_key':
            case 'africas_talking_api_key':
                // Don't sanitize passwords/API keys, just trim whitespace
                return trim($value);
            
            case 'pwa_theme_color':
            case 'pwa_background_color':
                // Validate hex color
                if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                    return strtoupper($value);
                }
                return '#000000'; // Default color if invalid
            
            case 'starting_member_no':
            case 'decimal_places':
            case 'smtp_port':
                return (int) $value;
            
            case 'members_sign_up':
            case 'pwa_enabled':
            case 'pwa_shortcut_dashboard':
            case 'pwa_shortcut_transactions':
            case 'pwa_shortcut_loans':
            case 'pwa_shortcut_deposit':
            case 'pwa_shortcut_profile':
            case 'pwa_offline_support':
                return $value ? '1' : '0';
            
            default:
                return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Log settings changes for audit trail
     */
    private function logSettingsChange($settingType, $settingsData, Request $request)
    {
        try {
            // Get old values for comparison
            $oldSettings = [];
            foreach (array_keys($settingsData) as $key) {
                $oldSetting = \App\Models\TenantSetting::where('name', $key)
                    ->where('tenant_id', request()->tenant->id)
                    ->first();
                $oldSettings[$key] = $oldSetting ? $oldSetting->value : null;
            }

            // Log each changed setting
            foreach ($settingsData as $key => $newValue) {
                $oldValue = $oldSettings[$key] ?? null;
                
                if ($oldValue !== $newValue) {
                    // Mask sensitive values in logs
                    $logOldValue = $this->maskSensitiveValue($key, $oldValue);
                    $logNewValue = $this->maskSensitiveValue($key, $newValue);
                    
                    \App\Models\AuditLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'settings_update',
                        'resource' => 'tenant_setting',
                        'resource_id' => $key,
                        'old_value' => $logOldValue,
                        'new_value' => $logNewValue,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'tenant_id' => request()->tenant->id,
                        'metadata' => json_encode(['setting_type' => $settingType])
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::error('Failed to log settings change: ' . $e->getMessage());
        }
    }

    /**
     * Mask sensitive values in audit logs
     */
    private function maskSensitiveValue($key, $value)
    {
        $sensitiveFields = [
            'smtp_password',
            'twilio_auth_token', 
            'textmagic_api_key',
            'nexmo_api_secret',
            'infobip_api_key',
            'africas_talking_api_key'
        ];
        
        if (in_array($key, $sensitiveFields) && $value) {
            return '••••••••';
        }
        
        return $value;
    }

    /**
     * Check if a value is masked (should not be updated)
     */
    private function isMaskedValue($key, $value)
    {
        $sensitiveFields = [
            'smtp_password',
            'twilio_auth_token', 
            'textmagic_api_key',
            'nexmo_api_secret',
            'infobip_api_key',
            'africas_talking_api_key'
        ];
        
        return in_array($key, $sensitiveFields) && $value === '••••••••';
    }

}
