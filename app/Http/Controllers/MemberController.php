<?php
namespace App\Http\Controllers;

use App\Imports\MembersImport;
use App\Mail\GeneralMail;
use App\Models\CustomField;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\MemberRequestAccepted;
use App\Utilities\Overrider;
use App\Utilities\TextMessage;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class MemberController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        date_default_timezone_set(get_timezone());

        $this->middleware(function ($request, $next) {
            $route_name = request()->route()->getName();
            if ($route_name == 'members.store') {
                if (has_limit('members', 'member_limit') <= 0) {
                    if ($request->ajax()) {
                        return response()->json(['result' => 'error', 'message' => _lang('Sorry, Your have reached your limit ! You can update your subscription plan to increase your limit.')]);
                    }
                    return back()->with('error', _lang('Sorry, Your have reached your limit ! You can update your subscription plan to increase your limit.'));
                }
            }

            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $assets = ['datatable'];
        return view('backend.admin.member.list', compact('assets'));
    }

    public function get_table_data()
    {
        $tenantId = auth()->user()->tenant_id;
        
        $members = Member::withoutGlobalScopes()
            ->select([
                'members.id',
                'members.first_name',
                'members.last_name',
                'members.email',
                'members.member_no',
                'members.photo',
                'members.status',
                'branches.name as branch_name',
                DB::raw("(SELECT COUNT(*) FROM loans WHERE loans.borrower_id = members.id AND loans.tenant_id = {$tenantId}) as loans_count"),
                DB::raw("(SELECT COUNT(*) FROM transactions WHERE transactions.member_id = members.id AND transactions.tenant_id = {$tenantId}) as transactions_count"),
                DB::raw("(SELECT COUNT(*) FROM savings_accounts WHERE savings_accounts.member_id = members.id AND savings_accounts.tenant_id = {$tenantId}) as savings_accounts_count")
            ])
            ->leftJoin('branches', 'members.branch_id', '=', 'branches.id')
            ->where('members.tenant_id', $tenantId)
            ->where('members.status', 1)
            ->orderBy("members.id", "desc");

        return Datatables::eloquent($members)
            ->editColumn('branch_name', function ($member) {
                return $member->branch_name ?? _lang('Main Branch');
            })
            ->editColumn('photo', function ($member) {
                $photo = $member->photo != null ? profile_picture($member->photo) : asset('public/backend/images/avatar.png');
                return '<div class="profile_picture text-center">'
                    . '<img src="' . $photo . '" class="thumb-sm img-thumbnail">'
                    . '</div>';
            })
            ->addColumn('full_name', function ($member) {
                return $member->first_name . ' ' . $member->last_name;
            })
            ->addColumn('member_stats', function ($member) {
                return '<small class="text-muted">'
                    . 'Loans: ' . $member->loans_count . ' | '
                    . 'Transactions: ' . $member->transactions_count . ' | '
                    . 'Accounts: ' . $member->savings_accounts_count
                    . '</small>';
            })
            ->addColumn('action', function ($member) {
                $canDelete = $member->loans_count == 0 && 
                           $member->transactions_count == 0 && 
                           $member->savings_accounts_count == 0;
                           
                return '<div class="dropdown text-center">'
                . '<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action')
                . '&nbsp;</button>'
                . '<div class="dropdown-menu">'
                . '<a class="dropdown-item" href="' . route('members.edit', $member->id) . '"><i class="ti-pencil-alt"></i> ' . _lang('Edit') . '</a>'
                . '<a class="dropdown-item" href="' . route('members.show', $member->id) . '"><i class="ti-eye"></i>  ' . _lang('View') . '</a>'
                . '<a class="dropdown-item" href="' . route('member_documents.index', $member->id) . '"><i class="ti-files"></i>  ' . _lang('Documents') . '</a>'
                . ($canDelete ? '<form action="' . route('members.destroy', $member->id) . '" method="post">'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '<button class="dropdown-item btn-remove" type="submit"><i class="ti-trash"></i> ' . _lang('Delete') . '</button>'
                    . '</form>' : '<span class="dropdown-item text-muted"><i class="ti-info"></i> ' . _lang('Cannot delete - has related data') . '</span>')
                    . '</div>'
                    . '</div>';
            })
            ->setRowId(function ($member) {
                return "row_" . $member->id;
            })
            ->rawColumns(['photo', 'action', 'member_stats'])
            ->make(true);
    }

    public function pending_requests()
    {
        $data            = [];
        $data['members'] = Member::where('status', 0)
            ->withoutGlobalScopes(['status'])
            ->paginate(10);
        return view('backend.admin.member.pending_requests', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $customFields = CustomField::where('table', 'members')
            ->where('status', 1)
            ->orderBy("id", "asc")
            ->get();

        $memberNo = get_tenant_option('starting_member_no');
        return view('backend.admin.member.create', compact('customFields', 'memberNo'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validationRules = [
            'first_name'   => 'required|string|max:50|regex:/^[a-zA-Z\s]+$/',
            'last_name'    => 'required|string|max:50|regex:/^[a-zA-Z\s]+$/',
            'email'        => [
                'nullable',
                'email',
                'max:100',
                Rule::unique('members')->where(function ($query) {
                    return $query->where('tenant_id', app('tenant')->id);
                }),
            ],
            'member_no'    => 'required|string|max:50|unique:members',
            'country_code' => 'required_with:mobile|string|max:10',
            'mobile'       => 'nullable|string|max:20|regex:/^[\+]?[0-9\s\-\(\)]+$/',
            'business_name' => 'nullable|string|max:100',
            'gender'        => 'nullable|in:male,female,other',
            'city'          => 'nullable|string|max:100',
            'county'        => 'nullable|string|max:100',
            'zip'           => 'nullable|string|max:20',
            'address'       => 'nullable|string|max:500',
            'credit_source' => 'nullable|string|max:100',
            'photo'         => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            //User Login Attributes
            'name'         => 'required_if:client_login,1|string|max:191',
            'login_email'  => [
                'required_if:client_login,1',
                'email',
                'max:100',
                Rule::unique('users', 'email')->where(function ($query) {
                    return $query->where('tenant_id', app('tenant')->id);
                }),
            ],
            'password'     => 'required_if:client_login,1|string|min:6|max:20',
            'status'       => 'required_if:client_login,1|in:0,1',
        ];

        $validationMessages = [
            'name.required_if'           => 'Name is required',
            'login_email.required_if'    => 'Email is required',
            'password.required_if'       => 'Password is required',
            'country_code.required_with' => 'Country code is required',
        ];

        // Custom field validation
        $customFields = CustomField::where('table', 'members')
            ->orderBy("id", "desc")
            ->get();
        $customValidation = generate_custom_field_validation($customFields);

        $validationRules    = array_merge($validationRules, $customValidation['rules']);
        $validationMessages = array_merge($validationMessages, $customValidation['messages']);

        $validator = Validator::make($request->all(), $validationRules, $validationMessages);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('members.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $photo = 'default.png';
        if ($request->hasfile('photo')) {
            $file = $request->file('photo');
            
            // Validate file
            if (!$file->isValid()) {
                return redirect()->route('members.create')
                    ->with('error', _lang('Invalid file uploaded'))
                    ->withInput();
            }
            
            // Generate secure filename
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid() . '_' . time() . '.' . $extension;
            
            // Move file to secure location
            $file->move(public_path() . "/uploads/profile/", $filename);
            $photo = $filename;
        }

        DB::beginTransaction();

        // Store custom field data
        $customFieldsData = store_custom_field_data($customFields);

        //Create Login details
        if ($request->client_login == 1 && $request->tenant->package->member_portal == 1) {
            $user                  = new User();
            $user->name            = $request->input('name');
            $user->email           = $request->input('login_email');
            $user->password        = Hash::make($request->password);
            $user->user_type       = 'customer';
            $user->status          = $request->input('status');
            $user->profile_picture = $photo;
            $user->save();
        }

        $member             = new Member();
        $member->first_name = $request->input('first_name');
        $member->last_name  = $request->input('last_name');
        if (auth()->user()->user_type == 'admin') {
            $member->branch_id = $request->branch_id;
        } else {
            $member->branch_id = auth()->user()->branch_id;
        }
        if ($request->client_login == 1) {
            $member->user_id = $user->id;
        }
        $member->email         = $request->input('email');
        $member->country_code  = $request->input('country_code');
        $member->mobile        = $request->input('mobile');
        $member->business_name = $request->input('business_name');
        $member->member_no     = get_tenant_option('starting_member_no', $request->input('member_no'));
        $member->gender        = $request->input('gender');
        $member->city          = $request->input('city');
        $member->county        = $request->input('county');
        $member->zip           = $request->input('zip');
        $member->address       = $request->input('address');
        $member->credit_source = $request->input('credit_source');
        $member->photo         = $photo;
        $member->custom_fields = json_encode($customFieldsData);

        $member->save();

        //Increment Member No
        $memberNo = get_tenant_option('starting_member_no');
        if ($memberNo != '') {
            update_tenant_option('starting_member_no', $memberNo + 1);
        }

        $this->generateAccounts($member->id);

        DB::commit();

        if (! $request->ajax()) {
            return redirect()->route('members.show', $member->id)->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $member, 'table' => '#members_table']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $tenant, $id)
    {
        // SECURE: Add tenant validation and proper member access control
        $member = Member::where('tenant_id', app('tenant')->id)
                        ->where('id', $id)
                        ->firstOrFail();
        $assets       = ['datatable'];
        $customFields = CustomField::where('table', 'members')
            ->where('status', 1)
            ->orderBy("id", "asc")
            ->get();

        return view('backend.admin.member.view', compact('member', 'id', 'customFields', 'assets'));
    }

    public function get_member_transaction_data($tenant, $member_id)
    {
        $transactions = Transaction::select([
                'transactions.*',
                'members.first_name as member_first_name',
                'members.last_name as member_last_name',
                'savings_accounts.account_number',
                'savings_products.name as savings_type_name',
                'currency.name as currency_name'
            ])
            ->leftJoin('members', 'transactions.member_id', '=', 'members.id')
            ->leftJoin('savings_accounts', 'transactions.savings_account_id', '=', 'savings_accounts.id')
            ->leftJoin('savings_products', 'savings_accounts.savings_product_id', '=', 'savings_products.id')
            ->leftJoin('currency', 'savings_products.currency_id', '=', 'currency.id')
            ->where('transactions.member_id', $member_id)
            ->orderBy("transactions.trans_date", "desc");

        return Datatables::eloquent($transactions)
            ->editColumn('member_first_name', function ($transactions) {
                return $transactions->member_first_name . ' ' . $transactions->member_last_name;
            })
            ->editColumn('dr_cr', function ($transactions) {
                return strtoupper($transactions->dr_cr);
            })
            ->editColumn('status', function ($transactions) {
                return transaction_status($transactions->status);
            })
            ->editColumn('amount', function ($transaction) {
                $symbol = $transaction->dr_cr == 'dr' ? '-' : '+';
                $class  = $transaction->dr_cr == 'dr' ? 'text-danger' : 'text-success';
                return '<span class="' . $class . '">' . $symbol . ' ' . decimalPlace($transaction->amount, currency_symbol($transaction->currency_name)) . '</span>';
            })
            ->editColumn('type', function ($transaction) {
                return str_replace('_', ' ', $transaction->type);
            })
            ->filterColumn('member_first_name', function ($query, $keyword) {
                $query->where(function($q) use ($keyword) {
                    $q->where("members.first_name", "like", "{$keyword}%")
                      ->orWhere("members.last_name", "like", "{$keyword}%");
                });
            }, true)
            ->addColumn('action', function ($transaction) {
                return '<div class="dropdown text-center">'
                . '<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action')
                . '&nbsp;</button>'
                . '<div class="dropdown-menu">'
                . '<a class="dropdown-item" href="' . route('transactions.edit', $transaction['id']) . '"><i class="ti-pencil-alt"></i> ' . _lang('Edit') . '</a>'
                . '<a class="dropdown-item" href="' . route('transactions.show', $transaction['id']) . '"><i class="ti-eye"></i>  ' . _lang('View') . '</a>'
                . '<form action="' . route('transactions.destroy', $transaction['id']) . '" method="post">'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '<button class="dropdown-item btn-remove" type="submit"><i class="ti-trash"></i> ' . _lang('Delete') . '</button>'
                    . '</form>'
                    . '</div>'
                    . '</div>';
            })
            ->setRowId(function ($transaction) {
                return "row_" . $transaction->id;
            })
            ->rawColumns(['action', 'status', 'amount'])
            ->make(true);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $tenant, $id)
    {
        $customFields = CustomField::where('table', 'members')
            ->where('status', 1)
            ->orderBy("id", "asc")
            ->get();
        // SECURE: Add tenant validation for member edit
        $member = Member::where('tenant_id', app('tenant')->id)
                        ->where('id', $id)
                        ->firstOrFail();
        if (! $request->ajax()) {
            return view('backend.admin.member.edit', compact('member', 'id', 'customFields'));
        } else {
            return view('backend.admin.member.modal.edit', compact('member', 'id', 'customFields'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $tenant, $id)
    {
        // SECURE: Add tenant validation for member edit
        $member = Member::where('tenant_id', app('tenant')->id)
                        ->where('id', $id)
                        ->firstOrFail();

        // ENHANCED: More secure validation rules
        $validationRules = [
            'first_name'   => 'required|string|max:50|regex:/^[a-zA-Z\s\-\']+$/|min:2',
            'last_name'    => 'required|string|max:50|regex:/^[a-zA-Z\s\-\']+$/|min:2',
            'email'        => [
                'nullable',
                'email:rfc,dns',
                'max:100',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
                Rule::unique('members')->where(function ($query) {
                    return $query->where('tenant_id', app('tenant')->id);
                })->ignore($id),
            ],
            'member_no'    => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('members')->ignore($id),
            ],
            'country_code' => 'required_with:mobile|string|max:10|regex:/^\+?[0-9]{1,4}$/',
            'mobile'       => 'nullable|string|max:20|regex:/^[\+]?[0-9\s\-\(\)]{7,20}$/',
            'business_name' => 'nullable|string|max:100|regex:/^[a-zA-Z0-9\s\-\'\.&]+$/',
            'gender'        => 'nullable|in:male,female,other',
            'city'          => 'nullable|string|max:100|regex:/^[a-zA-Z\s\-\']+$/',
            'county'        => 'nullable|string|max:100|regex:/^[a-zA-Z\s\-\']+$/',
            'zip'           => 'nullable|string|max:20|regex:/^[a-zA-Z0-9\s\-]+$/',
            'address'       => 'nullable|string|max:500|regex:/^[a-zA-Z0-9\s\-\'\.\,\#]+$/',
            'credit_source' => 'nullable|string|max:100|regex:/^[a-zA-Z\s\-\']+$/',
            'photo'         => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'name'         => 'required_if:client_login,1|string|max:191',
            'login_email'  => [
                'required_if:client_login,1',
                'email',
                'max:100',
                Rule::unique('users', 'email')->where(function ($query) {
                    return $query->where('tenant_id', app('tenant')->id);
                })->ignore($member->user_id),
            ],
            'password'     => 'nullable|string|min:6|max:20',
            'status'       => 'required_if:client_login,1|in:0,1',
        ];

        $validationMessages = [
            'name.required_if'           => 'Name is required',
            'login_email.required_if'    => 'Email is required',
            'password.required_if'       => 'Password is required',
            'country_code.required_with' => 'Country code is required',
        ];

        // Custom field validation
        $customFields = CustomField::where('table', 'members')
            ->orderBy("id", "desc")
            ->get();
        $customValidation = generate_custom_field_validation($customFields, true);

        $validationRules    = array_merge($validationRules, $customValidation['rules']);
        $validationMessages = array_merge($validationMessages, $customValidation['messages']);

        $validator = Validator::make($request->all(), $validationRules, $validationMessages);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('members.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        if ($request->hasfile('photo')) {
            $file = $request->file('photo');
            
            // Validate file
            if (!$file->isValid()) {
                return redirect()->route('members.edit', $id)
                    ->with('error', _lang('Invalid file uploaded'))
                    ->withInput();
            }
            
            // Generate secure filename
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid() . '_' . time() . '.' . $extension;
            
            // Move file to secure location
            $file->move(public_path() . "/uploads/profile/", $filename);
            $photo = $filename;
        }

        DB::beginTransaction();

        // Store custom field data
        $customFieldsData = store_custom_field_data($customFields, json_decode($member->custom_fields, true));

        if ($request->client_login == 1 && $request->tenant->package->member_portal == 1) {
            if ($member->user_id != null) {
                $user = User::find($member->user_id);
            } else {
                $user = new User();
            }
            $user->name   = $request->input('name');
            $user->email  = $request->input('login_email');
            $user->status = $request->input('status');
            if ($request->password) {
                $user->password = Hash::make($request->password);
            }
            $user->user_type = 'customer';
            $user->save();
        }

        $member->first_name = $request->input('first_name');
        $member->last_name  = $request->input('last_name');
        if (auth()->user()->user_type == 'admin') {
            $member->branch_id = $request->branch_id;
        } else {
            $member->branch_id = auth()->user()->branch_id;
        }
        if ($request->client_login == 1) {
            $member->user_id = $user->id;
        }
        $member->email         = $request->input('email');
        $member->country_code  = $request->input('country_code');
        $member->mobile        = $request->input('mobile');
        $member->business_name = $request->input('business_name');
        $member->member_no     = $request->input('member_no');
        $member->gender        = $request->input('gender');
        $member->city          = $request->input('city');
        $member->county        = $request->input('county');
        $member->zip           = $request->input('zip');
        $member->address       = $request->input('address');
        $member->credit_source = $request->input('credit_source');
        if ($request->hasfile('photo')) {
            $member->photo = $photo;
        }
        $member->custom_fields = json_encode($customFieldsData);

        $member->save();

        DB::commit();

        if (! $request->ajax()) {
            return redirect()->route('members.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $member, 'table' => '#members_table']);
        }
    }

    public function send_email(Request $request)
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        Overrider::load("Settings");

        $validator = Validator::make($request->all(), [
            'user_email' => 'required',
            'subject'    => 'required',
            'message'    => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)
                    ->withInput();
            }
        }

        //Send email
        $subject = $request->input("subject");
        $message = $request->input("message");

        $mail          = new \stdClass();
        $mail->subject = $subject;
        $mail->body    = $message;

        try {
            Mail::to($request->user_email)->send(new GeneralMail($mail));
        } catch (\Exception $e) {
            if (! $request->ajax()) {
                return back()->with('error', _lang('Sorry, Error Occured !'));
            } else {
                return response()->json(['result' => 'error', 'message' => _lang('Sorry, Error Occured !')]);
            }
        }

        if (! $request->ajax()) {
            return back()->with('success', _lang('Email Send Sucessfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Email Send Sucessfully'), 'data' => $contact]);
        }
    }

    public function send_sms(Request $request)
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $validator = Validator::make($request->all(), [
            'phone'   => 'required|regex:/^[\+]?[0-9\s\-\(\)]{8,20}$/',
            'message' => 'required|string|max:160|min:1',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)
                    ->withInput();
            }
        }

        //Send message
        $message = $request->input("message");

        if (get_tenant_option('sms_gateway') == 'none') {
            return back()->with('error', _lang('Sorry, SMS Gateway is disabled !'));
        }

        try {
            $sms = new TextMessage();
            $result = $sms->send($request->phone, $message);
            
            if (!$result) {
                \Log::warning('SMS send failed', [
                    'phone' => $request->phone,
                    'user_id' => auth()->id(),
                    'ip' => $request->ip()
                ]);
                
                if (! $request->ajax()) {
                    return back()->with('error', _lang('SMS delivery failed. Please try again later.'));
                } else {
                    return response()->json(['result' => 'error', 'message' => _lang('SMS delivery failed. Please try again later.')]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('SMS Send Exception', [
                'error' => $e->getMessage(),
                'phone' => $request->phone,
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if (! $request->ajax()) {
                return back()->with('error', _lang('Sorry, Error Occured !'));
            } else {
                return response()->json(['result' => 'error', 'message' => _lang('Sorry, Error Occured !')]);
            }
        }

        if (! $request->ajax()) {
            return back()->with('success', _lang('SMS Send Sucessfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('SMS Send Sucessfully'), 'data' => $contact]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant, $id)
    {
        try {
            $member = Member::findOrFail($id);
            
            // Check if member can be deleted
            if (!$member->canBeDeleted()) {
                $reasons = [];
                if ($member->loans()->where('status', 'active')->count() > 0) {
                    $reasons[] = _lang('active loans');
                }
                if ($member->transactions()->where('trans_date', '>=', now()->subDays(30))->count() > 0) {
                    $reasons[] = _lang('recent transactions');
                }
                if ($member->savings_accounts()->where('status', 1)->where('balance', '>', 0)->count() > 0) {
                    $reasons[] = _lang('active savings accounts');
                }
                
                return redirect()->route('members.index')
                    ->with('error', _lang('Cannot delete member with: ') . implode(', ', $reasons));
            }
            
            DB::beginTransaction();
            
            // Delete associated user account if exists
            if ($member->user) {
                $member->user->delete();
            }
            
            // Delete member
            $member->delete();
            
            DB::commit();
            
            // Log the deletion
            \Log::info('Member deleted', [
                'member_id' => $member->id,
                'member_name' => $member->first_name . ' ' . $member->last_name,
                'deleted_by' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id
            ]);
            
            return redirect()->route('members.index')
                ->with('success', _lang('Member deleted successfully'));
                
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Member deletion failed', [
                'member_id' => $id,
                'error' => $e->getMessage(),
                'deleted_by' => auth()->id()
            ]);
            
            return redirect()->route('members.index')
                ->with('error', _lang('Error deleting member'));
        }
    }

    public function accept_request(Request $request, $tenant, $id)
    {
        // Query member once at the beginning with tenant validation
        $member = Member::where('tenant_id', app('tenant')->id)
                        ->where('id', $id)
                        ->firstOrFail();

        // Ensure member is in pending status
        if ($member->status !== 0) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Member request is not pending')]);
            }
            return back()->with('error', _lang('Member request is not pending'));
        }

        if ($request->isMethod('get')) {
            return view('backend.admin.member.modal.accept_request', compact('member'));
        }

        // POST method - validate member number with tenant scope
        $validator = Validator::make($request->all(), [
            'member_no' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('members')->where(function ($query) {
                    return $query->where('tenant_id', app('tenant')->id);
                })->ignore($id),
            ],
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        DB::beginTransaction();

        try {
            // Update member details
            $member->member_no = $request->member_no;
            $member->status = 1;
            $member->save();

            // Update user status if user exists
            if ($member->user) {
                $member->user->status = 1;
                $member->user->save();
            }

            // Generate accounts for the member
            $this->generateAccounts($member->id);

            DB::commit();

            // Send notification if member is now active
            if ($member->status == 1) {
                try {
                    $member->notify(new MemberRequestAccepted($member));
                } catch (\Exception $e) {
                    \Log::warning('Failed to send member acceptance notification', [
                        'member_id' => $member->id,
                        'error' => $e->getMessage(),
                        'tenant_id' => app('tenant')->id
                    ]);
                }
            }

            // Log successful acceptance
            \Log::info('Member request accepted', [
                'member_id' => $member->id,
                'member_no' => $request->member_no,
                'accepted_by' => auth()->id(),
                'tenant_id' => app('tenant')->id
            ]);

            if (! $request->ajax()) {
                return redirect()->route('members.index')->with('success', _lang('Member Request Accepted'));
            } else {
                return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Member Request Accepted'), 'data' => $member, 'table' => '#members_table']);
            }

        } catch (\Exception $e) {
            DB::rollback();
            
            \Log::error('Member acceptance failed', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
                'accepted_by' => auth()->id(),
                'tenant_id' => app('tenant')->id
            ]);

            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Failed to accept member request')]);
            } else {
                return back()->with('error', _lang('Failed to accept member request'));
            }
        }
    }

    public function reject_request($tenant, $id)
    {
        // SECURE: Add tenant validation for member edit
        $member = Member::where('tenant_id', app('tenant')->id)
                        ->where('id', $id)
                        ->firstOrFail();
        $member->user->delete();
        $member->delete();
        return redirect()->back()->with('error', _lang('Member Request Rejected'));
    }

    public function import(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('backend.admin.member.import');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $validator = Validator::make($request->all(), [
                'file' => 'required|mimes:xlsx|max:10240', // 10MB limit
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            try {
                $rows = Excel::toArray([], $request->file('file'));

                if (has_limit('members', 'member_limit') < (count($rows[0]) - 1)) {
                    return back()->with('error', _lang('Sorry, Your have reached your limit ! You can update your subscription plan to increase your limit.'));
                }

                DB::beginTransaction();

                $previous_rows = Member::count();

                $import = new MembersImport();
                Excel::import($import, $request->file('file'));

                $current_rows = Member::count();
                $new_rows = $current_rows - $previous_rows;

                DB::commit();

                // Get import results
                $importedCount = $import->getImportedCount();
                $errors = $import->getErrors();

                $message = $importedCount . ' ' . _lang('members imported successfully');
                
                if (!empty($errors)) {
                    $message .= '. ' . count($errors) . ' ' . _lang('rows had errors');
                    \Log::warning('Member import completed with errors', [
                        'imported_count' => $importedCount,
                        'error_count' => count($errors),
                        'errors' => $errors,
                        'user_id' => auth()->id()
                    ]);
                }

                if ($importedCount == 0) {
                    return back()->with('error', _lang('Nothing Imported, Data may already exists or contains errors !'));
                }

                return back()->with('success', $message);

            } catch (\Exception $e) {
                DB::rollback();
                
                \Log::error('Member import failed', [
                    'error' => $e->getMessage(),
                    'file' => $request->file('file')->getClientOriginalName(),
                    'user_id' => auth()->id()
                ]);

                return back()->with('error', _lang('Import failed: ') . $e->getMessage());
            }
        }
    }

    private function generateAccounts($member_id)
    {
        $tenant = app('tenant');
        
        // Check if VSLA is enabled and auto-create member accounts is enabled
        $shouldCreateAccounts = true;
        if ($tenant->isVslaEnabled()) {
            $vslaSettings = $tenant->vslaSettings;
            if ($vslaSettings && !$vslaSettings->auto_create_member_accounts) {
                $shouldCreateAccounts = false;
            }
        }
        
        if (!$shouldCreateAccounts) {
            return;
        }
        
        // SECURE: Use database transactions to prevent race conditions
        DB::transaction(function () use ($member_id) {
            $accountsTypes = SavingsProduct::where('auto_create', 1)
                ->lockForUpdate() // Prevent concurrent access
                ->get();
                
            foreach ($accountsTypes as $accountType) {
                // SECURE: Generate unique account number atomically
                $nextAccountNumber = $accountType->starting_account_number;
                
                // Check if account number already exists
                $existingAccount = SavingsAccount::where('account_number', 
                    $accountType->account_number_prefix . $nextAccountNumber
                )->first();
                
                if ($existingAccount) {
                    // Find next available account number
                    do {
                        $nextAccountNumber++;
                        $existingAccount = SavingsAccount::where('account_number', 
                            $accountType->account_number_prefix . $nextAccountNumber
                        )->first();
                    } while ($existingAccount);
                }
                
                $savingsaccount = new SavingsAccount();
                $savingsaccount->account_number     = $accountType->account_number_prefix . $nextAccountNumber;
                $savingsaccount->member_id          = $member_id;
                $savingsaccount->savings_product_id = $accountType->id;
                $savingsaccount->status             = 1;
                $savingsaccount->opening_balance    = 0;
                $savingsaccount->description        = '';
                $savingsaccount->created_user_id    = auth()->id();
                $savingsaccount->tenant_id          = app('tenant')->id;

                $savingsaccount->save();

                // SECURE: Update account number atomically
                $accountType->starting_account_number = $nextAccountNumber + 1;
                $accountType->save();
            }
        });
    }
}
