<?php

use App\Models\Message;
use App\Models\Page;
use App\Models\SubscriptionPayment;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (! function_exists('_lang')) {
    function _lang($string = '') {

        $target_lang = get_language();

        if ($target_lang == '') {
            $target_lang = "language";
        }

        if (file_exists(resource_path() . "/language/$target_lang.php")) {
            include resource_path() . "/language/$target_lang.php";
        } else {
            include resource_path() . "/language/language.php";
        }

        if (array_key_exists($string, $language)) {
            return $language[$string];
        } else {
            return $string;
        }
    }
}

if (! function_exists('_dlang')) {
    function _dlang($string = '') {

        //Get Target language
        $target_lang = get_language();

        if ($target_lang == '') {
            $target_lang = 'language';
        }

        if (file_exists(resource_path() . "/language/$target_lang.php")) {
            include resource_path() . "/language/$target_lang.php";
        } else {
            include resource_path() . "/language/language.php";
        }

        if (array_key_exists($string, $language)) {
            return $language[$string];
        } else {
            return $string;
        }
    }
}

if (! function_exists('startsWith')) {
    function startsWith($haystack, $needle) {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }
}

if (! function_exists('get_initials')) {
    function get_initials($string) {
        $words    = explode(" ", $string);
        $initials = null;
        foreach ($words as $w) {
            $initials .= $w[0];
        }
        return $initials;
    }
}

if (! function_exists('create_option')) {
    function create_option($table, $value, $display, $selected = '', $where = NULL, $concat = ' ') {
        $options   = '';
        $condition = '';
        if ($where != NULL) {
            $condition .= "WHERE ";
            foreach ($where as $key => $v) {
                $condition .= $key . "'" . $v . "' ";
            }
        }

        if (is_array($display)) {
            $display_array = $display;
            $display       = $display_array[0];
            $display1      = $display_array[1];
        }

        $query = DB::select("SELECT * FROM $table $condition ORDER BY $display asc");
        foreach ($query as $d) {
            if ($selected != '' && $selected == $d->$value) {
                if (! isset($display_array)) {
                    $options .= "<option value='" . $d->$value . "' selected='true'>" . ucwords($d->$display) . "</option>";
                } else {
                    $options .= "<option value='" . $d->$value . "' selected='true'>" . ucwords($d->$display . $concat . $d->$display1) . "</option>";
                }
            } else {
                if (! isset($display_array)) {
                    $options .= "<option value='" . $d->$value . "'>" . ucwords($d->$display) . "</option>";
                } else {
                    $options .= "<option value='" . $d->$value . "'>" . ucwords($d->$display . $concat . $d->$display1) . "</option>";
                }
            }
        }

        echo $options;
    }
}

if (! function_exists('object_to_string')) {
    function object_to_string($object, $col, $quote = false) {
        $string = "";
        foreach ($object as $data) {
            if ($quote == true) {
                $string .= "'" . $data->$col . "', ";
            } else {
                $string .= $data->$col . ", ";
            }
        }
        $string = substr_replace($string, "", -2);
        return $string;
    }
}

if (! function_exists('get_table')) {
    function get_table($table, $where = NULL) {
        $condition = "";
        if ($where != NULL) {
            $condition .= "WHERE ";
            foreach ($where as $key => $v) {
                $condition .= $key . "'" . $v . "' ";
            }
        }
        $query = DB::select("SELECT * FROM $table $condition");
        return $query;
    }
}

if (! function_exists('is_admin')) {
    function is_admin() {
        $user = auth()->user();
        return $user && ($user->user_type === 'superadmin' || $user->user_type === 'admin');
    }
}

if (! function_exists('is_super_admin')) {
    function is_super_admin() {
        $user = auth()->user();
        return $user && $user->user_type === 'superadmin';
    }
}

if (! function_exists('is_tenant_admin')) {
    function is_tenant_admin() {
        $user = auth()->user();
        return $user && $user->user_type === 'admin';
    }
}

if (! function_exists('has_permission')) {
    function has_permission($name) {
        $user = auth()->user();
        
        // Super admin has access to everything
        if ($user->user_type === 'superadmin') {
            return true;
        }
        
        // Tenant admin has access to everything within their tenant
        if ($user->user_type === 'admin') {
            return true;
        }
        
        // For other users, check role-based permissions
        $permission_list = $user->role->permissions;
        $permission      = $permission_list->firstWhere('permission', $name);

        if ($permission != null) {
            return true;
        }
        return false;
    }
}

if (! function_exists('has_vsla_access')) {
    function has_vsla_access() {
        $user = auth()->user();
        
        // Super admin and tenant admin always have VSLA access
        if ($user->user_type === 'superadmin' || $user->user_type === 'admin') {
            return true;
        }
        
        // Check if user has VSLA User role
        if ($user->role && $user->role->name === 'VSLA User') {
            return true;
        }
        
        // Check if user has any VSLA permissions
        $permission_list = $user->role->permissions;
        $vsla_permissions = $permission_list->filter(function ($permission) {
            return strpos($permission->permission, 'vsla.') === 0;
        });
        
        return $vsla_permissions->isNotEmpty();
    }
}

if (! function_exists('is_vsla_treasurer')) {
    function is_vsla_treasurer() {
        $user = auth()->user();
        
        // Super admin and tenant admin are considered treasurers
        if ($user->user_type === 'superadmin' || $user->user_type === 'admin') {
            return true;
        }
        
        // Check if user has a member record and is assigned as treasurer
        $member = $user->member;
        if ($member) {
            $tenant = app('tenant');
            $treasurerAssignment = \App\Models\VslaRoleAssignment::where('tenant_id', $tenant->id)
                ->where('member_id', $member->id)
                ->where('role', 'treasurer')
                ->where('is_active', true)
                ->first();
            
            return $treasurerAssignment !== null;
        }
        
        return false;
    }
}

if (! function_exists('get_logo')) {
    function get_logo() {
        $logo = get_option("logo");

        if (isset(request()->tenant->id)) {
            $logo = get_tenant_option("logo", $logo);
        }

        if(app()->bound('tenant')){
            $logo = get_tenant_option("logo", $logo, app('tenant')->id);
        }

        if ($logo == "") {
            return asset("public/backend/images/company-logo.png");
        }
        return asset("public/uploads/media/$logo");
    }
}

if (! function_exists('get_favicon')) {
    function get_favicon() {
        $favicon = get_option("favicon");
        if ($favicon == "") {
            return asset("public/backend/images/favicon.png");
        }
        return asset("public/uploads/media/$favicon");
    }
}

if (! function_exists('profile_picture')) {
    function profile_picture($profile_picture = '') {
        if ($profile_picture == '') {
            $profile_picture = auth()->user()->profile_picture;
        }

        if ($profile_picture == '') {
            return asset('public/backend/images/avatar.png');
        }

        return asset('public/uploads/profile/' . $profile_picture);
    }
}

if (! function_exists('get_option')) {
    function get_option($name, $optional = '') {
        $value = Cache::get($name);

        if ($value == "") {
            $setting = DB::table('settings')->where('name', $name)->get();
            if (! $setting->isEmpty()) {
                $value = $setting[0]->value;
                Cache::put($name, $value);
            } else {
                $value = $optional;
            }
        }
        return $value;
    }
}

if (! function_exists('get_setting')) {
    function get_setting($settings, $name, $optional = '') {
        $row = $settings->firstWhere('name', $name);
        if ($row != null) {
            return $row->value;
        }
        return $optional;
    }
}

if (! function_exists('get_tenant_option')) {
    function get_tenant_option($name, $optional = '', $tenantId = '') {
        global $$name;

        if (${$name} != null) {
            return $$name;
        }

        if ($tenantId == '') {
            if (isset(request()->tenant->id)) {
                $setting = \App\Models\TenantSetting::withoutGlobalScopes()->where('name', $name)
                    ->where('tenant_id', request()->tenant->id)
                    ->first();
            } else {
                $setting = \App\Models\TenantSetting::where('name', $name)->first();
            }
        } else {
            $setting = \App\Models\TenantSetting::withoutGlobalScopes()->where('name', $name)
                ->where('tenant_id', $tenantId)
                ->first();
        }

        if ($setting) {
            $value = $setting->value;
        } else {
            $value = $optional;
        }

        ${$name} = $value;

        return $value;
    }
}

if (! function_exists('update_tenant_option')) {
    function update_tenant_option($name, $value, $tenantId = '') {
        $data          = [];
        $data['value'] = $value;

        if ($tenantId == '') {
            $tenantId = request()->tenant->id;
        }

        $data['tenant_id']  = $tenantId;
        $data['updated_at'] = \Carbon\Carbon::now();

        $setting = \App\Models\TenantSetting::where('name', $name)->where('tenant_id', $tenantId);

        if ($setting->exists()) {
            \App\Models\TenantSetting::where('name', $name)
                ->where('tenant_id', $tenantId)
                ->update($data);
        } else {
            $data['name']       = $name;
            $data['created_at'] = \Carbon\Carbon::now();
            \App\Models\TenantSetting::insert($data);
        }
    }
}

if (! function_exists('get_trans_option')) {
    function get_trans_option($name, $optional = '') {
        $setting = \App\Models\Setting::where('name', $name)->first();

        if ($setting) {
            $value = $setting->translation->value;
        } else {
            $value = $optional;
        }

        return $value;
    }
}

if (! function_exists('get_array_option')) {
    function get_array_option($name, $key = '', $optional = '') {
        if ($key == '') {
            if (session('language') == '') {
                $key = get_option('language');
                session(['language' => $key]);
            } else {
                $key = session('language');
            }
        }
        $setting = DB::table('settings')->where('name', $name)->get();
        if (! $setting->isEmpty()) {

            $value = $setting[0]->value;
            if (@unserialize($value) !== false) {
                $value = @unserialize($setting[0]->value);

                return isset($value[$key]) ? $value[$key] : $value[array_key_first($value)];
            }

            return $value;
        }
        return $optional;

    }
}

if (! function_exists('get_array_data')) {
    function get_array_data($data, $key = '') {
        if ($key == '') {
            if (session('language') == '') {
                $key = get_option('language');
                session(['language' => $key]);
            } else {
                $key = session('language');
            }
        }

        if (@unserialize($data) !== false) {
            $value = @unserialize($data);
            return isset($value[$key]) ? $value[$key] : $value[array_key_first($value)];
        }

        return $data;

    }
}

if (! function_exists('update_option')) {
    function update_option($name, $value) {
        date_default_timezone_set(get_timezone());

        $data               = [];
        $data['value']      = $value;
        $data['updated_at'] = \Carbon\Carbon::now();
        if (\App\Models\Setting::where('name', $name)->exists()) {
            \App\Models\Setting::where('name', $name)->update($data);
        } else {
            $data['name']       = $name;
            $data['created_at'] = \Carbon\Carbon::now();
            \App\Models\Setting::insert($data);
        }
        Cache::put($name, $value);
    }
}

if (! function_exists('timezone_list')) {

    function timezone_list() {
        $zones_array = [];
        $timestamp   = time();
        foreach (timezone_identifiers_list() as $key => $zone) {
            date_default_timezone_set($zone);
            $zones_array[$key]['ZONE'] = $zone;
            $zones_array[$key]['GMT']  = 'UTC/GMT ' . date('P', $timestamp);
        }
        return $zones_array;
    }

}

if (! function_exists('create_timezone_option')) {
    function create_timezone_option($old = "") {
        $option    = "";
        $timestamp = time();
        foreach (timezone_identifiers_list() as $key => $zone) {
            date_default_timezone_set($zone);
            $selected = $old == $zone ? "selected" : "";
            $option .= '<option value="' . $zone . '"' . $selected . '>' . 'GMT ' . date('P', $timestamp) . ' ' . $zone . '</option>';
        }
        echo $option;
    }

}

if (! function_exists('load_language')) {
    function load_language($active = '') {
        $path    = resource_path() . "/language";
        $files   = scandir($path);
        $options = "";

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if ($name == "." || $name == "" || $name == "language") {
                continue;
            }

            $selected = "";
            if ($active == $name) {
                $selected = "selected";
            } else {
                $selected = "";
            }

            $options .= "<option value='$name' $selected>" . explode('---', $name)[0] . "</option>";

        }
        echo $options;
    }
}

if (! function_exists('get_language_list')) {
    function get_language_list() {
        $path  = resource_path() . "/language";
        $files = scandir($path);
        $array = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if ($name == "." || $name == "" || $name == "language" || $name == "flags") {
                continue;
            }

            $array[] = $name;

        }
        return $array;
    }
}

if (! function_exists('process_string')) {

    function process_string($search_replace, $string) {
        $result = $string;
        foreach ($search_replace as $key => $value) {
            $result = str_replace($key, $value, $result);
        }
        return $result;
    }

}

if (! function_exists('permission_list')) {
    function permission_list() {
        $permission_list = \App\Models\AccessControl::where("role_id", Auth::user()->role_id)
            ->pluck('permission')->toArray();
        return $permission_list;
    }
}

if (! function_exists('get_country_list')) {
    function get_country_list($old_data = '') {
        if ($old_data == '') {
            echo file_get_contents(app_path() . '/Helpers/country.txt');
        } else {
            $pattern      = '<option value="' . $old_data . '">';
            $replace      = '<option value="' . $old_data . '" selected="selected">';
            $country_list = file_get_contents(app_path() . '/Helpers/country.txt');
            $country_list = str_replace($pattern, $replace, $country_list);
            echo $country_list;
        }
    }
}

if (! function_exists('status')) {
    function status($status) {
        if ($status == 0) {
            return "<span class='badge badge-danger'>" . _lang('Deactivated') . "</span>";
        } else if ($status == 1) {
            return "<span class='badge badge-success'>" . _lang('Active') . "</span>";
        }
    }
}

if (! function_exists('transaction_status')) {
    function transaction_status($status, $html = true) {
        if ($status == 0) {
            return $html == true ? "<span class='badge badge-warning'>" . _lang('Pending') . "</span>" : _lang('Pending');
        } else if ($status == 1) {
            return $html == true ? "<span class='badge badge-danger'>" . _lang('Cancelled') . "</span>" : _lang('Cancelled');
        } else if ($status == 2) {
            return $html == true ? "<span class='badge badge-success'>" . _lang('Completed') . "</span>" : _lang('Completed');
        }
    }
}

if (! function_exists('show_status')) {
    function show_status($value, $status) {
        return "<span class='badge badge-$status'>" . $value . "</span>";
    }
}

if (! function_exists('user_status')) {
    function user_status($status) {
        if ($status == 1) {
            return "<span class='badge badge-success'>" . _lang('Active') . "</span>";
        } else if ($status == 0) {
            return "<span class='badge badge-danger'>" . _lang('In Active') . "</span>";
        }
    }
}

if (! function_exists('general_ledger_link')) {
    function general_ledger_link($id) {
        $from_date = now()->startOfYear()->toDateString();
        $to_date   = now()->endOfYear()->toDateString();
        return route('reports.generalLedger') . "?from_date=$from_date&to_date=$to_date&account_id=$id";
    }
}

//Request Count
if (! function_exists('request_count')) {
    function request_count($request, $html = false, $class = "sidebar-notification-count") {
        $userId             = auth()->id();
        $notification_count = 0;

        if ($request == 'unread_contact_message') {
            $notification_count = \App\Models\ContactMessage::where('status', 0)->count();
        }

        if ($request == 'users') {
            $notification_count = User::count();
        }

        if ($request == 'messages') {
            $notification_count = Message::where('recipient_id', $userId)
                ->where('status', 'unread')
                ->count();
        }

        if ($request == 'pending_payments') {
            $notification_count = SubscriptionPayment::where('status', 0)->count();
        }

        if ($request == 'pending_loans') {
            $notification_count = \App\Models\Loan::where('status', 0)->count();
        }

        if ($request == 'deposit_requests') {
            $notification_count = \App\Models\DepositRequest::where('status', 0)->count();
        }

        if ($request == 'withdraw_requests') {
            $notification_count = \App\Models\WithdrawRequest::where('status', 0)->count();
        }

        if ($request == 'member_requests') {
            $notification_count = \App\Models\Member::withoutGlobalScopes(['status'])->where('status', 0)->count();
        }

        if ($request == 'upcoming_repayments') {
            $startDate          = Carbon::today();
            $endDate            = Carbon::today()->addDays(7);
            $notification_count = \App\Models\LoanRepayment::whereBetween('repayment_date', [$startDate, $endDate])
                ->where('status', 0)
                ->count();
        }

        if ($html == false) {
            return $notification_count;
        }

        if ($notification_count > 0) {
            //return '<div class="circle-animation"></div>';
            return '<span class="' . $class . '">' . $notification_count . '</span>';
        }

    }
}

if (! function_exists('is_decimal')) {
    function is_decimal($n) {
        return is_numeric($n) && floor($n) != $n;
    }
}

if (! function_exists('file_icon')) {
    function file_icon($mime_type) {
        static $font_awesome_file_icon_classes = [
            // Images
            'image'                                                                     => 'fa-file-image',
            // Audio
            'audio'                                                                     => 'fa-file-audio',
            // Video
            'video'                                                                     => 'fa-file-video',
            // Documents
            'application/pdf'                                                           => 'fa-file-pdf',
            'application/msword'                                                        => 'fa-file-word',
            'application/vnd.ms-word'                                                   => 'fa-file-word',
            'application/vnd.oasis.opendocument.text'                                   => 'fa-file-word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml'            => 'fa-file-word',
            'application/vnd.ms-excel'                                                  => 'fa-file-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml'               => 'fa-file-excel',
            'application/vnd.oasis.opendocument.spreadsheet'                            => 'fa-file-excel',
            'application/vnd.ms-powerpoint'                                             => 'fa-file-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml'              => 'ffa-file-powerpoint',
            'application/vnd.oasis.opendocument.presentation'                           => 'fa-file-powerpoint',
            'text/plain'                                                                => 'fa-file-alt',
            'text/html'                                                                 => 'fa-file-code',
            'application/json'                                                          => 'fa-file-code',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'fa-file-word',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'fa-file-excel',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'fa-file-powerpoint',
            // Archives
            'application/gzip'                                                          => 'fa-file-archive',
            'application/zip'                                                           => 'fa-file-archive',
            'application/x-zip-compressed'                                              => 'fa-file-archive',
            // Misc
            'application/octet-stream'                                                  => 'fa-file-archive',
        ];

        if (isset($font_awesome_file_icon_classes[$mime_type])) {
            return $font_awesome_file_icon_classes[$mime_type];
        }

        $mime_group = explode('/', $mime_type, 2)[0];
        return (isset($font_awesome_file_icon_classes[$mime_group])) ? $font_awesome_file_icon_classes[$mime_group] : 'fa-file';
    }
}

if (! function_exists('convert_currency')) {
    function convert_currency($from_currency, $to_currency, $amount) {
        if ($from_currency == $to_currency || $amount == 0) {
            return (double) $amount;
        }
        $currency1 = \App\Models\Currency::where('name', $from_currency)->first()->exchange_rate;
        $currency2 = \App\Models\Currency::where('name', $to_currency)->first()->exchange_rate;

        $converted_output = ($amount / $currency1) * $currency2;
        return $converted_output;
    }
}

if (! function_exists('convert_currency_2')) {
    function convert_currency_2($currency1_rate, $currency2_rate, $amount) {
        $currency1 = $currency1_rate;
        $currency2 = $currency2_rate;

        $converted_output = ($amount / $currency1) * $currency2;
        return $converted_output;
    }
}

if (! function_exists('get_country_codes')) {
    function get_country_codes() {
        return json_decode(file_get_contents(app_path() . '/Helpers/country.json'), true);
    }
}

if (! function_exists('xss_clean')) {
    function xss_clean($data) {
        // Fix &entity\n;
        $data = str_replace(['&amp;', '&lt;', '&gt;'], ['&amp;amp;', '&amp;lt;', '&amp;gt;'], $data);
        $data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
        $data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
        $data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

        // Remove any attribute starting with "on" or xmlns
        $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);

        // Remove javascript: and vbscript: protocols
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);

        // Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);

        // Remove namespaced elements (we do not need them)
        $data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);

        do {
            // Remove really unwanted tags
            $old_data = $data;
            $data     = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
        } while ($old_data !== $data);

        // we are done...
        return $data;
    }
}

if (! function_exists('get_account_details')) {
    function get_account_details($member_id) {
        $accounts = App\Models\SavingsAccount::select('savings_accounts.*', DB::raw("((SELECT IFNULL(SUM(amount),0)
        FROM transactions WHERE dr_cr = 'cr' AND status = 2 AND savings_account_id = savings_accounts.id) -
        (SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = 'dr'
        AND status != 1 AND savings_account_id = savings_accounts.id)) as balance"), DB::raw("(SELECT IFNULL(SUM(guarantors.amount),0)
        FROM guarantors JOIN loans ON loans.id=guarantors.loan_id WHERE (loans.status = 0 OR loans.status = 1)
        AND guarantors.savings_account_id=savings_accounts.id) as blocked_amount"))
            ->with(['member', 'savings_type', 'savings_type.currency'])
            ->where('savings_accounts.member_id', $member_id)
            ->orderBy('id', 'desc')
            ->get();

        return $accounts;
    }
}

if (! function_exists('get_account_balance')) {
    function get_account_balance($account_id, $member_id) {
        $blockedAmount = App\Models\Guarantor::join('loans', 'loans.id', 'guarantors.loan_id')
            ->whereRaw('loans.status = 0 OR loans.status = 1')
            ->where('guarantors.member_id', $member_id)
            ->where('guarantors.savings_account_id', $account_id)
            ->sum('guarantors.amount');

        $result = DB::select("SELECT ((SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = 'cr'
	    AND member_id = $member_id AND savings_account_id = $account_id AND status = 2) - (SELECT IFNULL(SUM(amount),0) FROM transactions
	    WHERE dr_cr = 'dr' AND member_id = $member_id AND savings_account_id = $account_id AND status != 1)) as balance");

        return $result[0]->balance - $blockedAmount;
    }
}

if (! function_exists('get_bank_account_balance')) {
    function get_bank_account_balance($bank_account_id) {
        $bankAccount = App\Models\BankAccount::find($bank_account_id);
        
        if (!$bankAccount) {
            return 0;
        }

        // Use cached balance if available
        $cacheKey = "bank_balance_{$bank_account_id}";
        $cachedBalance = Cache::get($cacheKey);
        
        if ($cachedBalance !== null) {
            return $cachedBalance;
        }

        // Calculate balance from transactions
        $result = DB::table('bank_transactions')
            ->where('bank_account_id', $bank_account_id)
            ->where('status', App\Models\BankTransaction::STATUS_APPROVED)
            ->selectRaw('
                SUM(CASE WHEN dr_cr = "cr" THEN amount ELSE 0 END) as credits,
                SUM(CASE WHEN dr_cr = "dr" THEN amount ELSE 0 END) as debits
            ')
            ->first();

        $balance = ($result->credits ?? 0) - ($result->debits ?? 0);
        
        // Cache the result for 5 minutes
        Cache::put($cacheKey, $balance, 300);
        
        return $balance;
    }
}

if (! function_exists('validate_bank_transaction')) {
    function validate_bank_transaction($bank_account_id, $amount, $dr_cr, $include_pending = false) {
        $bankAccount = App\Models\BankAccount::find($bank_account_id);
        
        if (!$bankAccount) {
            return ['valid' => false, 'message' => 'Bank account not found'];
        }

        if (!$bankAccount->is_active) {
            return ['valid' => false, 'message' => 'Bank account is not active'];
        }

        $availableBalance = get_bank_account_balance($bank_account_id);
        
        if (!$include_pending) {
            // Subtract pending debit transactions
            $pendingDebits = DB::table('bank_transactions')
                ->where('bank_account_id', $bank_account_id)
                ->where('dr_cr', 'dr')
                ->where('status', App\Models\BankTransaction::STATUS_PENDING)
                ->sum('amount');
            
            $availableBalance -= $pendingDebits;
        }

        if ($dr_cr === 'dr') {
            $requiredBalance = $amount;
            
            if (!$bankAccount->allow_negative_balance) {
                $requiredBalance += $bankAccount->minimum_balance;
            }

            if ($availableBalance < $requiredBalance) {
                return [
                    'valid' => false, 
                    'message' => "Insufficient balance. Available: " . number_format($availableBalance, 2) . 
                               ", Required: " . number_format($requiredBalance, 2)
                ];
            }
        }

        return ['valid' => true, 'message' => 'Transaction is valid'];
    }
}

if (! function_exists('get_blocked_balance')) {

    function get_blocked_balance($account_id, $member_id) {
        $blockedAmount = App\Models\Guarantor::join('loans', 'loans.id', 'guarantors.loan_id')
            ->whereRaw('loans.status = 0 OR loans.status = 1')
            ->where('guarantors.member_id', $member_id)
            ->where('guarantors.savings_account_id', $account_id)
            ->sum('guarantors.amount');

        return $blockedAmount;
    }
}

if (! function_exists('get_all_account_details')) {
    function get_all_account_details($savings_product_id = null, $member_id = null) {
        $accounts = App\Models\SavingsAccount::select('savings_accounts.*', DB::raw("((SELECT IFNULL(SUM(amount),0)
        FROM transactions WHERE dr_cr = 'cr' AND status = 2 AND savings_account_id = savings_accounts.id) -
        (SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = 'dr'
        AND status != 1 AND savings_account_id = savings_accounts.id)) as balance"), DB::raw("(SELECT IFNULL(SUM(guarantors.amount),0)
        FROM guarantors JOIN loans ON loans.id=guarantors.loan_id WHERE (loans.status = 0 OR loans.status = 1)
        AND guarantors.savings_account_id=savings_accounts.id) as blocked_amount"))
            ->with(['member', 'savings_type', 'savings_type.currency'])
            ->when($savings_product_id, function ($query, $savings_product_id) {
                return $query->where('savings_accounts.savings_product_id', $savings_product_id);
            })
            ->when($member_id, function ($query, $member_id) {
                return $query->where('member_id', $member_id);
            })
            ->orderBy('id', 'desc')
            ->get();

        return $accounts;
    }
}

// convert seconds into time
if (! function_exists('time_from_seconds')) {
    function time_from_seconds($seconds) {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds - ($h * 3600) - ($m * 60);
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}

/* Intelligent Functions */
if (! function_exists('get_language')) {
    function get_language($force = false) {

        if (isset(request()->model_language)) {
            return request()->model_language;
        }

        $language = $force == false ? session('language') : '';

        if ($language == '') {
            if (isset(request()->tenant)) {
                $language = get_tenant_option('language', Cache::get('language'));
            } else {
                $language = Cache::get('language');
            }
        }

        if ($language == '') {
            $language = get_option('language');
            if ($language == '') {
                \Cache::put('language', 'language');
            } else {
                \Cache::put('language', $language);
            }

        }
        return $language;
    }
}

if (! function_exists('get_timezone')) {
    function get_timezone() {
        if (isset(request()->tenant->id)) {
            $timezone = get_tenant_option('timezone', 'Africa/Nairobi');
            return $timezone;
        }
        $timezone = Cache::get('timezone');

        if ($timezone == '') {
            $timezone = get_option('timezone', 'Africa/Nairobi');
            \Cache::put('timezone', $timezone);
        }

        return $timezone;
    }
}

if (! function_exists('generate_input_field')) {
    function generate_input_field($field, $initialValue = null) {
        $field_label = $field->field_name;
        $field_name  = str_replace(' ', '_', $field->field_name);
        $field_type  = $field->field_type;
        $validation  = $field->is_required;

        $value = $initialValue == null ? old('custom_fields.' . $field_name) : $initialValue;

        $field_html = '';
        if ($field_type == 'text') {
            $field_html = '<input type="text" class="form-control" name="custom_fields[' . $field_name . ']" value="' . $value . '" placeholder="' . $field_label . '"' . $validation . '>';
        } elseif ($field_type == 'textarea') {
            $field_html = '<textarea class="form-control" name="custom_fields[' . $field_name . ']" placeholder="' . $field_label . '"' . $validation . '>' . $value . '</textarea>';
        } elseif ($field_type == 'number') {
            $field_html = '<input type="number" class="form-control" name="custom_fields[' . $field_name . ']" value="' . $value . '" placeholder="' . $field_label . '"' . $validation . '>';
        } elseif ($field_type == 'select') {
            $selectOptions = '<option value="">' . _lang('Select One') . '</option>';
            foreach (explode(",", $field->default_value) as $option) {
                $option = trim($option);
                $selectOptions .= "<option value='$option'>$option</option>";
            }
            $field_html = '<select class="form-control auto-select" name="custom_fields[' . $field_name . ']" data-selected="' . $value . '" data-placeholder="' . $field_label . '"' . $validation . '>' . $selectOptions . '</select>';
        } elseif ($field_type == 'file') {
            if ($initialValue == null) {
                $field_html = '<input type="file" class="file-uploader" name="custom_fields[' . $field_name . ']" data-value="' . $value . '" data-placeholder="' . $field_label . '"' . $validation . '>';
            } else {
                $field_html = '<input type="file" class="file-uploader" name="custom_fields[' . $field_name . ']" data-value="' . $value . '" data-placeholder="' . $field_label . '">';
            }
        }

        return $field_html;
    }
}

if (! function_exists('generate_custom_field_validation')) {
    function generate_custom_field_validation($custom_fields, $edit = false) {
        $validationRules    = [];
        $validationMessages = [];

        if (! empty($custom_fields)) {
            foreach ($custom_fields as $field) {
                $field->field_name                                      = str_replace(' ', '_', $field->field_name);
                $validationRules['custom_fields.' . $field->field_name] = $field->is_required;

                if ($field->field_type == 'file') {
                    $file_required = $field->is_required;
                    if ($edit == true) {
                        $file_required = 'nullable';
                    }
                    $max_size                                               = $field->max_size * 1024;
                    $validationRules['custom_fields.' . $field->field_name] = $file_required . "|mimes:jpeg,jpg,png,pdf|max:$max_size";
                }

                if ($field->is_required == 'required') {
                    $validationMessages['custom_fields.' . $field->field_name . '.required'] = 'The ' . $field->field_name . ' is required';
                }

                if ($field->field_type == 'file') {
                    $validationMessages['custom_fields.' . $field->field_name . '.mimes'] = 'The ' . $field->field_name . ' must be a file of type: jpeg, jpg, png, pdf';
                    $validationMessages['custom_fields.' . $field->field_name . '.max']   = 'The ' . $field->field_name . ' may not be greater than ' . $field->max_size . ' MB';
                }
            }
        }

        return [
            'rules'    => $validationRules,
            'messages' => $validationMessages,
        ];

    }
}

// Create function to store custom field data
if (! function_exists('store_custom_field_data')) {
    function store_custom_field_data($custom_fields, $existingData = null) {
        $data = [];
        if (! empty($custom_fields)) {
            foreach ($custom_fields as $field) {
                $field_name = str_replace(' ', '_', $field->field_name);
                $field_type = $field->field_type;

                if ($field_type == 'file') {
                    if (request()->hasFile('custom_fields.' . $field_name)) {
                        $file      = request()->file('custom_fields.' . $field_name);
                        $file_name = $file->getClientOriginalName();
                        $file_name = str_replace(' ', '_', $file_name);
                        $file_name = time() . md5(uniqid()) . '_' . $file_name;
                        $file->move('public/uploads/media/', $file_name);
                        $field_value = $file_name;
                    } else {
                        $field_value = $existingData[$field->field_name]['field_value'] ?? null;
                        //$field_value = null;
                    }
                } else {
                    $field_value = request()->custom_fields[$field_name] ?? null;
                }

                $data[$field_name] = [
                    'field_name'  => $field_name,
                    'field_type'  => $field_type,
                    'field_value' => $field_value,
                ];
            }
        }
        return $data;

    }
}


///
if (! function_exists('generate_input_field_2')) {
    function generate_input_field_2($field) {
        $field_type  = $field->field_type;
        $field_name  = $field->field_name;
        $field_label = $field->field_label;
        $validation  = $field->validation;

        $field_required = $validation == 'required' ? 'required' : '';

        $field_html = '';
        if ($field_type == 'text') {
            $field_html = '<input type="text" class="form-control" name="requirements[' . $field_name . ']" value="' . old('requirements.' . $field_name) . '" placeholder="' . $field_label . '"' . $field_required . '>';
        } elseif ($field_type == 'textarea') {
            $field_html = '<textarea class="form-control" name="requirements[' . $field_name . ']" placeholder="' . $field_label . '"' . $field_required . '>' . old('requirements.' . $field_name) . '</textarea>';
        } elseif ($field_type == 'file') {
            $field_html = '<input type="file" class="file-uploader" name="requirements[' . $field_name . ']" data-placeholder="' . $field_label . '"' . $field_required . '>';
        } elseif ($field_type == 'number') {
            $field_html = '<input type="number" class="form-control" name="requirements[' . $field_name . ']" value="' . old('requirements.' . $field_name) . '" placeholder="' . $field_label . '"' . $field_required . '>';
        }

        return $field_html;
    }
}

if (! function_exists('generate_custom_field_validation_2')) {
    function generate_custom_field_validation_2($custom_fields) {
        $validationRules    = [];
        $validationMessages = [];

        if (! empty($custom_fields)) {
            foreach ($custom_fields as $field) {
                $validationRules['requirements.' . $field->field_name] = $field->validation;
                if ($field->field_type == 'file') {
                    $max_size                                              = $field->max_size * 1024;
                    $validationRules['requirements.' . $field->field_name] = $field->validation . "|mimes:jpeg,jpg,png,pdf|max:$max_size";
                }

                if ($field->validation == 'required') {
                    $validationMessages[$field->field_name . '.required'] = 'The ' . $field->field_name . ' is required';
                }

                if ($field->field_type == 'file') {
                    $validationMessages[$field->field_name . '.mimes'] = 'The ' . $field->field_name . ' must be a file of type: jpeg, jpg, png, pdf';
                    $validationMessages[$field->field_name . '.max']   = 'The ' . $field->field_name . ' may not be greater than ' . $field->max_size . ' MB';
                }
            }
        }

        return [
            'rules'    => $validationRules,
            'messages' => $validationMessages,
        ];

    }
}

if (! function_exists('store_custom_field_data_2')) {
    function store_custom_field_data_2($custom_fields) {
        $requirements = [];
        if (! empty($custom_fields)) {
            foreach ($custom_fields as $field) {
                $field_label = $field->field_label;
                $field_name  = $field->field_name;
                $field_type  = $field->field_type;

                if ($field_type == 'file') {
                    if (request()->hasFile('requirements.' . $field_name)) {
                        $file      = request()->file('requirements.' . $field_name);
                        $file_name = $file->getClientOriginalName();
                        $file_name = str_replace(' ', '_', $file_name);
                        $file_name = time() . md5(uniqid()) . '_' . $file_name;
                        $file->move('public/uploads/media/', $file_name);
                        $field_value = $file_name;
                    } else {
                        $field_value = null;
                    }
                } else {
                    $field_value = request()->requirements[$field_name] ?? null;
                }

                array_push($requirements, [
                    'field_label' => $field_label,
                    'field_name'  => $field_name,
                    'field_type'  => $field_type,
                    'field_value' => $field_value,
                ]);
            }
        }
        return $requirements;

    }
}

if (! function_exists('get_country_code')) {
    function get_country_code($ip = null) {
        $ip  = $ip ?? $_SERVER['REMOTE_ADDR'];
        $url = "http://ip-api.com/json/$ip";

        $response = file_get_contents($url);
        $data     = json_decode($response, true);

        if ($data['status'] == 'success') {
            $countryCode = $data['countryCode'];
            return $countryCode;
        }
        return null;
    }
}

//** Currency Related Functions **//
if (! function_exists('get_currency_list')) {
    function get_currency_list($old_data = '', $serialize = false) {
        $currency_list = file_get_contents(app_path() . '/Helpers/currency.txt');

        if ($old_data == "") {
            echo $currency_list;
        } else {
            if ($serialize == true) {
                $old_data = unserialize($old_data);
                for ($i = 0; $i < count($old_data); $i++) {
                    $pattern       = '<option value="' . $old_data[$i] . '">';
                    $replace       = '<option value="' . $old_data[$i] . '" selected="selected">';
                    $currency_list = str_replace($pattern, $replace, $currency_list);
                }
                echo $currency_list;
            } else {
                $pattern       = '<option value="' . $old_data . '">';
                $replace       = '<option value="' . $old_data . '" selected="selected">';
                $currency_list = str_replace($pattern, $replace, $currency_list);
                echo $currency_list;
            }
        }
    }
}

if (! function_exists('decimalPlace')) {
    function decimalPlace($number, $symbol = '') {
        if ($symbol == '') {
            return money_format_2($number);
        }

        if (get_currency_position() == 'right') {
            return money_format_2($number) . get_currency_symbol($symbol);
        } else {
            return get_currency_symbol($symbol) . money_format_2($number);
        }
    }
}

if (! function_exists('money_format_2')) {
    function money_format_2($floatcurr) {
        $decimal_place = get_option('decimal_places', 2);
        $decimal_sep   = get_option('decimal_sep', '.');
        $thousand_sep  = get_option('thousand_sep', ',');

        if (isset(request()->tenant->id)) {
            $decimal_place = get_tenant_option('decimal_places', $decimal_place);
            $decimal_sep   = get_tenant_option('decimal_sep', $decimal_sep);
            $thousand_sep  = get_tenant_option('thousand_sep', $thousand_sep);
        }

        $decimal_sep  = $decimal_sep == '' ? ' ' : $decimal_sep;
        $thousand_sep = $thousand_sep == '' ? ' ' : $thousand_sep;

        return number_format($floatcurr, $decimal_place, $decimal_sep, $thousand_sep);
    }
}

if (! function_exists('get_currency_position')) {
    function get_currency_position() {
        $currency_position = Cache::get('currency_position');

        if (isset(request()->tenant->id)) {
            $currency_position = get_tenant_option('currency_position', $currency_position);
            return $currency_position;
        }

        if ($currency_position == '') {
            $currency_position = get_option('currency_position', 'left');
            \Cache::put('currency_position', $currency_position);
        }

        return $currency_position;
    }
}

if (! function_exists('get_kenyan_counties')) {
    function get_kenyan_counties() {
        return [
            'Baringo' => 'Baringo',
            'Bomet' => 'Bomet',
            'Bungoma' => 'Bungoma',
            'Busia' => 'Busia',
            'Elgeyo-Marakwet' => 'Elgeyo-Marakwet',
            'Embu' => 'Embu',
            'Garissa' => 'Garissa',
            'Homa Bay' => 'Homa Bay',
            'Isiolo' => 'Isiolo',
            'Kajiado' => 'Kajiado',
            'Kakamega' => 'Kakamega',
            'Kericho' => 'Kericho',
            'Kiambu' => 'Kiambu',
            'Kilifi' => 'Kilifi',
            'Kirinyaga' => 'Kirinyaga',
            'Kisii' => 'Kisii',
            'Kisumu' => 'Kisumu',
            'Kitui' => 'Kitui',
            'Kwale' => 'Kwale',
            'Laikipia' => 'Laikipia',
            'Lamu' => 'Lamu',
            'Machakos' => 'Machakos',
            'Makueni' => 'Makueni',
            'Mandera' => 'Mandera',
            'Marsabit' => 'Marsabit',
            'Meru' => 'Meru',
            'Migori' => 'Migori',
            'Mombasa' => 'Mombasa',
            'Murang\'a' => 'Murang\'a',
            'Nairobi' => 'Nairobi',
            'Nakuru' => 'Nakuru',
            'Nandi' => 'Nandi',
            'Narok' => 'Narok',
            'Nyamira' => 'Nyamira',
            'Nyandarua' => 'Nyandarua',
            'Nyeri' => 'Nyeri',
            'Samburu' => 'Samburu',
            'Siaya' => 'Siaya',
            'Taita-Taveta' => 'Taita-Taveta',
            'Tana River' => 'Tana River',
            'Tharaka-Nithi' => 'Tharaka-Nithi',
            'Trans Nzoia' => 'Trans Nzoia',
            'Turkana' => 'Turkana',
            'Uasin Gishu' => 'Uasin Gishu',
            'Vihiga' => 'Vihiga',
            'Wajir' => 'Wajir',
            'West Pokot' => 'West Pokot'
        ];
    }
}

if (! function_exists('get_credit_source_options')) {
    function get_credit_source_options() {
        return [
            'Bank Loan' => 'Bank Loan',
            'Microfinance Institution' => 'Microfinance Institution',
            'SACCO' => 'SACCO',
            'Chama/Investment Group' => 'Chama/Investment Group',
            'Family/Friends' => 'Family/Friends',
            'Personal Savings' => 'Personal Savings',
            'Government Loan' => 'Government Loan',
            'Mobile Money Loan' => 'Mobile Money Loan',
            'Employer Loan' => 'Employer Loan',
            'Asset Financing' => 'Asset Financing',
            'Trade Credit' => 'Trade Credit',
            'Others' => 'Others'
        ];
    }
}

if (! function_exists('base_currency_id')) {
    function base_currency_id() {
        if (app()->bound('tenant')) {
            $tenant   = app('tenant');
            $tenantId = $tenant->id ?? 'default';
            $cacheKey = "base_currency_id_{$tenantId}";

            $base_currency_id = Cache::get($cacheKey);

            if ($base_currency_id == '') {
                $currency = \App\Models\Currency::where("base_currency", 1)
                    ->where('tenant_id', $tenant->id)
                    ->first();
                if ($currency) {
                    $base_currency_id = $currency->id;
                    Cache::put($cacheKey, $base_currency_id);
                }
            }

            if (! $base_currency_id) {
                $currency = \App\Models\Currency::where('tenant_id', $tenant->id)->first();
                if ($currency) {
                    $base_currency_id = $currency->id;
                    Cache::put($cacheKey, $base_currency_id);
                }
            }

            return $base_currency_id;
        }
        return null;
    }
}

if (! function_exists('get_base_currency')) {
    function get_base_currency() {
        $tenant   = app('tenant');
        $tenantId = $tenant->id ?? 'default';
        $cacheKey = "base_currency_{$tenantId}";

        $base_currency = Cache::get($cacheKey);

        if ($base_currency == '') {
            $currency = \App\Models\Currency::where("base_currency", 1)
                ->where('tenant_id', $tenant->id)
                ->first();
            if ($currency) {
                $base_currency = $currency->name;
                Cache::put($cacheKey, $base_currency);
            }
        }

        if (! $base_currency) {
            $currency = \App\Models\Currency::where('tenant_id', $tenant->id)->first();
            if ($currency) {
                $base_currency = $currency->name;
                Cache::put($cacheKey, $base_currency);
            }
        }

        return $base_currency;
    }
}

if (! function_exists('get_currency')) {
    function get_currency($currency_id) {
        $currency = \App\Models\Currency::find($currency_id);
        return $currency;
    }
}

if (! function_exists('get_currency_symbol')) {
    function get_currency_symbol($currency_code) {
        include app_path() . '/Helpers/currency_symbol.php';

        if (array_key_exists($currency_code, $currency_symbols)) {
            return $currency_symbols[$currency_code];
        }
        return $currency_code;

    }
}

if (! function_exists('currency_symbol')) {
    function currency_symbol($currency = '') {
        if ($currency == '') {
            if (isset(request()->tenant->id)) {
                $currency = get_base_currency();
            } else {
                $currency = get_option('currency', 'USD');
            }
        }
        return html_entity_decode(get_currency_symbol($currency), ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('currency')) {
    function currency($currency = '') {
        if ($currency == '') {
            if (isset(request()->tenant->id)) {
                $currency = get_base_currency();
                return html_entity_decode(get_currency_symbol($currency), ENT_QUOTES, 'UTF-8');
            }
            $currency = get_option('currency', 'USD');
        }

        return html_entity_decode(get_currency_symbol($currency), ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('update_membership_date')) {
    function update_membership_date($package, $subscription_date) {
        if ($package->package_type == 'monthly') {
            $newDate = date('Y-m-d', strtotime($subscription_date . ' + 1 months'));
        } else if ($package->package_type == 'yearly') {
            $newDate = date('Y-m-d', strtotime($subscription_date . ' + 1 years'));
        } else if ($package->package_type == 'lifetime') {
            $newDate = date('Y-m-d', strtotime($subscription_date . ' + 25 years'));
        }
        return $newDate;
    }
}

if (! function_exists('has_limit')) {
    function has_limit($table, $packageColumn, $totalLimit = true, $filter = null) {
        $tenant       = request()->tenant;
        $package      = $tenant->package;
        $packageLimit = $package->{$packageColumn};

        if ($packageLimit == '-1') {
            return 999;
        }

        $filter = $filter == null ? "tenant_id = $tenant->id" : $filter;

        if ($totalLimit == true) {
            $query = DB::select("SELECT COUNT(id) as total FROM $table WHERE $filter");
        } else {
            $subscription_date = $tenant->getRawOriginal('subscription_date');
            $query             = DB::select("SELECT COUNT(id) as total FROM $table WHERE date(created_at) >= '$subscription_date' AND $filter");
        }

        return $packageLimit - $query[0]->total;
    }
}

if (! function_exists('get_date_format')) {
    function get_date_format() {
        if (isset(request()->tenant->id)) {
            $date_format = get_tenant_option('date_format', get_option('date_format', 'Y-m-d'));
            return $date_format;
        }

        $date_format = Cache::get('date_format');

        if ($date_format == '') {
            $date_format = get_option('date_format', 'Y-m-d');
            \Cache::put('date_format', $date_format);
        }

        return $date_format;
    }
}

if (! function_exists('get_time_format')) {
    function get_time_format() {
        if (isset(request()->tenant->id)) {
            $time_format = get_tenant_option('time_format', get_option('time_format', 'H:i'));
            $time_format = $time_format == 24 ? 'H:i' : 'h:i A';
            return $time_format;
        }
        $time_format = Cache::get('time_format');

        if ($time_format == '') {
            $time_format = get_option('time_format', 'H:i');
            \Cache::put('time_format', $time_format);
        }

        $time_format = $time_format == 24 ? 'H:i' : 'h:i A';

        return $time_format;
    }
}

if (! function_exists('processShortCode')) {
    function processShortCode($body, $replaceData = []) {
        $message = $body;
        foreach ($replaceData as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
        }
        return $message;
    }
}

if (! function_exists('get_page')) {
    function get_page_title($slug) {
        $defaultPages = ['home', 'about', 'features', 'pricing', 'blogs', 'faq', 'contact'];
        if (in_array($slug, $defaultPages)) {
            $string = ucwords($slug);
            return _dlang($string);
        }
        
        $pageData  = json_decode(get_trans_option($slug . '_page'));
        if($pageData != null){
            return $pageData->title;
        }

        $page = Page::where('slug', $slug)->first();
        return $page ? $page->translation->title : ucwords($slug);
    }
}

/* Create Option Field */
if (! function_exists('create_option_field')) {
    function create_option_field($option_fields) {
        if ($option_fields != null) {
            $form = '<form action="" method="post">';
            foreach ($option_fields as $name => $val) {

                $column = 'col-md-12';
                if (isset($val['column'])) {
                    $column = $val['column'];
                }

                $required = '';
                if ($val['required'] == true) {
                    $required = 'required';
                }

                if ($val['type'] == 'text') {
                    $form .= '<div class="' . $column . '"><div class="form-group">';
                    $form .= '<label>' . $val['label'] . '</label>';
                    $form .= '<input type="text" class="form-control ' . $name . '" name="' . $name . '" value="' . $val['value'] . '" data-change-class="' . $val['change']['class'] . '" data-change-action="' . $val['change']['action'] . '" ' . $required . '>';
                    $form .= '</div></div>';
                } else if ($val['type'] == 'textarea') {
                    $form .= '<div class="' . $column . '"><div class="form-group">';
                    $form .= '<label>' . $val['label'] . '</label>';
                    $form .= '<textarea class="form-control ' . $name . '" name="' . $name . '" data-change-class="' . $val['change']['class'] . '" data-change-action="' . $val['change']['action'] . '" ' . $required . '>' . $val['value'] . '</textarea>';
                    $form .= '</div></div>';
                } else if ($val['type'] == 'html') {
                    $form .= '<div class="' . $column . '"><div class="form-group">';
                    $form .= '<label>' . $val['label'] . '</label>';
                    $form .= '<textarea class="form-control ' . $name . '" name="' . $name . '" data-change-class="' . $val['change']['class'] . '" data-change-action="' . $val['change']['action'] . '" rows="8" ' . $required . '>' . $val['value'] . '</textarea>';
                    $form .= '</div></div>';
                } else if ($val['type'] == 'select') {
                    $form .= '<div class="' . $column . '"><div class="form-group">';
                    $form .= '<label>' . $val['label'] . '</label>';
                    $form .= '<select class="form-control ' . $name . '" name="' . $name . '" data-change-class="' . $val['change']['class'] . '" data-change-action="' . $val['change']['action'] . '" ' . $required . '>';
                    foreach ($val['options'] as $option => $display) {
                        $selectedOption = $val['value'] == $option ? 'selected' : '';
                        $form .= '<option value="' . $option . '"' . $selectedOption . '>' . $display . '</option>';
                    }
                    $form .= '</select>';
                    $form .= '</div></div>';

                }

            }
            $form .= '<div class="col-md-12 mt-2"><button type="submit" class="btn btn-primary btn-block"><i class="ti-check-box mr-1"></i>' . _lang('Save Setting') . '</button></div></form>';
            $script = '</script>';

            return $form;
        } else {
            $form = '<form action="" method="post"><div class="col-12"><h5 class="text-center">' . _lang('No option available') . '</h5></div></form>';
            return $form;
        }
    }
}

if (! function_exists('process_loan_fee')) {
    function process_loan_fee($fee_name, $member_id, $account_id, $amount, $charge, $fee_type, $loan_id) {
        if ($charge <= 0) {
            return;
        }

        if ($fee_type == 1) {
            $charge = ($charge / 100) * $amount;
        }

        $fee                     = new Transaction();
        $fee->trans_date         = now();
        $fee->member_id          = $member_id;
        $fee->savings_account_id = $account_id;
        $fee->amount             = $charge;
        $fee->charge             = $charge;
        $fee->dr_cr              = 'dr';
        $fee->type               = $fee_name;
        $fee->method             = 'Online';
        $fee->status             = 2;
        $fee->created_user_id    = auth()->id();
        $fee->description        = ucwords(str_replace('_', ' ', $fee_name));
        $fee->loan_id            = $loan_id;
        $fee->save();
    }
}

if (! function_exists('formatAmount')) {
    function formatAmount($amount, $currency = '') {
        if ($currency == '') {
            if (isset(request()->tenant->id)) {
                $currency = get_base_currency();
            } else {
                $currency = get_option('currency', 'USD');
            }
        }

        $formatted_amount = money_format_2($amount);
        
        if (get_currency_position() == 'right') {
            return $formatted_amount . ' ' . get_currency_symbol($currency);
        } else {
            return get_currency_symbol($currency) . ' ' . $formatted_amount;
        }
    }
}