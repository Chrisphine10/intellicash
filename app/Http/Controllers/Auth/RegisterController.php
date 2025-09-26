<?php
namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Member;
use App\Models\Tenant;
use App\Utilities\Overrider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;

class RegisterController extends Controller {
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
     */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('guest');
        Overrider::load("Settings");
    }

    /**
     * Show the application registration form.
     *
     * @return \Illuminate\View\View
     */
    public function showRegistrationForm() {
        if (get_option('member_signup') != 1) {
            return back();
        }
        return view('auth.register');
    }

    public function showMembersSignupForm() {
        if (get_tenant_option('members_sign_up', 0, app('tenant')->id) == 0) {
            return back();
        }
        $businessName = get_tenant_option('business_name', app('tenant')->name, app('tenant')->id);
        return view('auth.members-register', ['postUrl' => route('tenant.members_signup', app('tenant')->slug), 'businessName' => $businessName]);
    }

    public function members_signup(Request $request) {
        if (get_tenant_option('members_sign_up', 0, app('tenant')->id) == 0) {
            return back();
        }

        config(['recaptchav3.sitekey' => get_option('recaptcha_site_key')]);
        config(['recaptchav3.secret' => get_option('recaptcha_secret_key')]);

        $validator = Validator::make($request->all(), [
            'first_name'           => ['required', 'string', 'max:50'],
            'last_name'            => ['required', 'string', 'max:50'],
            'email'                => ['required', 'string', 'email', 'max:191', 'unique:users', 'unique:members'],
            'email'                => [
                'required',
                'string',
                'max:191',
                'email',
                Rule::unique('members')->where(function ($query) {
                    return $query->where('tenant_id', app('tenant')->id);
                }),
            ],
            'country_code'         => ['required'],
            'mobile'               => [
                'required',
                'numeric',
                Rule::unique('members')->where(function ($query) {
                    return $query->where('tenant_id', app('tenant')->id);
                }),
            ],
            'password'             => ['required', 'string', 'min:6', 'confirmed'],
            'branch_id'            => ['nullable', Rule::exists('branches', 'id')->where(function ($query) {
                return $query->where('tenant_id', app('tenant')->id);
            })],
            'gender'               => ['required'],
            'city'                 => ['required'],
            'county'               => ['required', 'in:' . implode(',', array_keys(get_kenyan_counties()))],
            'zip'                  => ['required'],
            'address'              => ['required'],
            'credit_source'        => ['required', 'in:' . implode(',', array_keys(get_credit_source_options()))],
            'g-recaptcha-response' => get_option('enable_recaptcha', 0) == 1 ? 'required|recaptchav3:register,0.5' : '',
        ], [
            'g-recaptcha-response.recaptchav3' => _lang('Recaptcha error!'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        DB::beginTransaction();

        $user = User::create([
            'name'            => $request->first_name . ' ' . $request->last_name,
            'email'           => $request->email,
            'user_type'       => 'customer',
            'status'          => 0,
            'profile_picture' => 'default.png',
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'tenant_id'       => app('tenant')->id,
        ]);

        $member                = new Member();
        $member->first_name    = $request->first_name;
        $member->last_name     = $request->last_name;
        $member->user_id       = $user->id;
        $member->branch_id     = $request->branch_id;
        $member->email         = $request->email;
        $member->country_code  = $request->country_code;
        $member->mobile        = $request->mobile;
        $member->business_name = isset($request->business_name) ? $request->business_name : '';
        $member->member_no     = get_tenant_option('starting_member_no', null, app('tenant')->id);
        $member->gender        = $request->gender;
        $member->city          = $request->city;
        $member->county        = $request->county;
        $member->zip           = $request->zip;
        $member->address       = $request->address;
        $member->credit_source = $request->credit_source;
        $member->photo         = 'default.png';
        $member->status        = 0;
        $member->tenant_id     = app('tenant')->id;

        $member->save();

        //Increment Member No
        $memberNo = get_tenant_option('starting_member_no', app('tenant')->id);
        if ($memberNo != '') {
            update_tenant_option('starting_member_no', $memberNo + 1, app('tenant')->id);
        }

        DB::commit();

        return back()->with('success', _lang('Your registration has been successfully completed. You will be notified once it has been approved by the relevant authorities.'));
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data) {
        config(['recaptchav3.sitekey' => get_option('recaptcha_site_key')]);
        config(['recaptchav3.secret' => get_option('recaptcha_secret_key')]);

        return Validator::make($data, [
            'name'                 => ['required', 'string', 'max:50'],
            'workspace'            => ['required', 'unique:tenants,slug', 'alpha_dash', 'max:30'],
            'email'                => [
                'required',
                'string',
                'email',
                Rule::unique('users')->where(function ($query) {
                    return $query->where('tenant_owner', 1)
                        ->orWhere('user_type', 'superadmin');
                }),
            ],
            'country_code'         => ['required'],
            'mobile'               => ['required', 'numeric', 'unique:users,mobile'],
            'password'             => ['required', 'string', 'min:6', 'confirmed'],
            'g-recaptcha-response' => get_option('enable_recaptcha', 0) == 1 ? 'required|recaptchav3:register,0.5' : '',
        ], [
            //'agree.required'                   => _lang('You must agree with our privacy policy and terms of use'),
            'g-recaptcha-response.recaptchav3' => _lang('Recaptcha error!'),
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data) {

        $tenant             = new Tenant();
        $tenant->slug       = $data['workspace'];
        $tenant->name       = $data['name'];
        $tenant->status     = 1;
        $tenant->package_id = $data['package_id'] ?? null;
        $tenant->save();

        // Get the Admin role for this tenant
        $adminRole = DB::table('roles')
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Admin')
            ->first();

        return User::create([
            'name'            => $data['name'],
            'email'           => $data['email'],
            'country_code'    => $data['country_code'],
            'mobile'          => $data['mobile'],
            'user_type'       => 'admin',
            'status'          => 1,
            'profile_picture' => 'default.png',
            'password'        => Hash::make($data['password']),
            'tenant_id'       => $tenant->id,
            'tenant_owner'    => 1,
            'role_id'         => $adminRole ? $adminRole->id : null,
        ]);
    }

    private function redirectTo() {
        return route('dashboard.index', ['tenant' => auth()->user()->tenant->slug]);
    }
}
