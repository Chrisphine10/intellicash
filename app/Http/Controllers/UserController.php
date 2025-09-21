<?php
namespace App\Http\Controllers;

use App\Models\User;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());

        $this->middleware(function ($request, $next) {
            $route_name = request()->route()->getName();
            if ($route_name == 'users.store') {
                if (has_limit('users', 'user_limit') <= 0) {
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
    public function index() {
        $assets = ['datatable'];
        return view('backend.admin.user.list', compact('assets'));
    }

    public function get_table_data() {
        // Get current tenant
        $tenant = app('tenant');
        
        $users = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where(function($query) {
                $query->where('user_type', 'admin')
                      ->orWhere('user_type', 'user');
            })
            ->select('users.*')
            ->with('role')
            ->orderBy("users.id", "desc");

        // Debug: Log the query and results
        \Log::info('User query SQL: ' . $users->toSql());
        \Log::info('User query bindings: ' . json_encode($users->getBindings()));
        \Log::info('Current user: ' . (auth()->check() ? auth()->user()->name . ' (tenant: ' . auth()->user()->tenant_id . ')' : 'Not authenticated'));
        \Log::info('Tenant context: ' . (app('tenant') ? app('tenant')->name . ' (ID: ' . app('tenant')->id . ')' : 'No tenant context'));
        \Log::info('Users found: ' . $users->count());

        return Datatables::eloquent($users)
            ->editColumn('name', function ($user) {
                return '<div class="d-flex align-items-center">'
                . '<img src="' . profile_picture($user->profile_picture) . '" class="thumb-sm img-thumbnail rounded-circle mr-3">'
                . '<div><span class="d-block text-height-0"><b>' . $user->name . '</b></span><span class="d-block">' . $user->email . '</span></div>'
                    . '</div>';
            })
            ->filterColumn('name', function ($query, $keyword) {
                return $query->where("name", "like", "{$keyword}%")
                    ->orWhere("email", "like", "{$keyword}%");
            }, true)
            ->editColumn('status', function ($user) {
                return status($user->status);
            })
            ->addColumn('action', function ($user) {
                return '<div class="dropdown text-center">'
                . '<button class="btn btn-outline-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action') . '</button>'
                . '<div class="dropdown-menu">'
                . '<a class="dropdown-item" href="' . route('users.edit', $user['id']) . '"><i class="ti-pencil-alt"></i> ' . _lang('Edit') . '</a>'
                . '<a class="dropdown-item" href="' . route('users.show', $user['id']) . '"><i class="ti-eye"></i>  ' . _lang('View') . '</a>'
                . '<form action="' . route('users.destroy', $user['id']) . '" method="post">'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '<button class="dropdown-item btn-remove" type="submit"><i class="ti-trash"></i> ' . _lang('Delete') . '</button>'
                    . '</form>'
                    . '</div>'
                    . '</div>';
            })
            ->setRowId(function ($user) {
                return "row_" . $user->id;
            })
            ->rawColumns(['name', 'membership_type', 'status', 'valid_to', 'action'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        $alert_col = 'col-lg-8 offset-lg-2';
        return view('backend.admin.user.create', compact('alert_col'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'name'            => 'required|max:60',
            'email'           => [
                'required',
                'email',
                Rule::unique('users')->where(function ($query) {
                    return $query->where('tenant_id', app('tenant')->id);
                }),
            ],
            'status'          => 'required',
            'profile_picture' => 'nullable|image|max:4096',
            'password'        => 'required|min:6',
            'country_code'    => [
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->filled('mobile') && empty($value)) {
                        $fail('The country code is required when mobile is provided.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('users.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $profile_picture = "default.png";
        if ($request->hasfile('profile_picture')) {
            $file            = $request->file('profile_picture');
            $profile_picture = rand() . time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/profile/", $profile_picture);
        }

        $user            = new User();
        $user->name      = $request->input('name');
        $user->email     = $request->input('email');
        $user->user_type = $request->input('user_type');
        $user->role_id   = $request->input('role_id');
        $user->tenant_id = app('tenant')->id;

        if ($request->branch_id == 'all_branch') {
            $user->branch_id         = null;
            $user->all_branch_access = 1;
        } else {
            $user->branch_id = $request->input('branch_id');
        }

        $user->status          = $request->input('status');
        $user->profile_picture = $profile_picture;
        $user->password        = Hash::make($request->password);
        $user->country_code    = $request->input('country_code');
        $user->mobile          = $request->input('mobile');
        $user->city            = $request->input('city');
        $user->state           = $request->input('state');
        $user->zip             = $request->input('zip');
        $user->address         = $request->input('address');

        $user->save();

        if ($user->id > 0) {
            return redirect()->route('users.index')->with('success', _lang('Saved Sucessfully'));
        } else {
            return redirect()->route('users.create')->with('error', _lang('Error Occured. Please try again'));
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $tenant, $id) {
        $user = User::staff()->find($id);
        return view('backend.admin.user.view', compact('user', 'id'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $tenant, $id) {
        $alert_col = 'col-lg-8 offset-lg-2';
        $user      = User::staff()->find($id);
        return view('backend.admin.user.edit', compact('user', 'id', 'alert_col'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $tenant, $id) {
        $validator = Validator::make($request->all(), [
            'name'            => 'required|max:191',
            'email'           => [
                'required',
                'email',
                Rule::unique('users')->where(function ($query) {
                    return $query->where('tenant_id', app('tenant')->id);
                })->ignore($id),
            ],
            'status'          => 'required',
            'profile_picture' => 'nullable|image|max:4096',
            'password'        => 'nullable|min:6',
            'country_code'    => [
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->filled('mobile') && empty($value)) {
                        $fail('The country code is required when mobile is provided.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('users.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        if ($request->hasfile('profile_picture')) {
            $file            = $request->file('profile_picture');
            $profile_picture = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/profile/", $profile_picture);
        }

        $user            = User::staff()->find($id);
        $user->name      = $request->input('name');
        $user->email     = $request->input('email');
        $user->user_type = $request->input('user_type');
        $user->role_id   = $request->input('role_id');

        if ($request->branch_id == 'all_branch') {
            $user->branch_id         = null;
            $user->all_branch_access = 1;
        } else {
            $user->branch_id = $request->input('branch_id');
        }

        $user->status = $request->input('status');

        if ($request->hasfile('profile_picture')) {
            $user->profile_picture = $profile_picture;
        }

        if ($request->password) {
            $user->password = Hash::make($request->password);
        }

        $user->country_code = $request->input('country_code');
        $user->mobile       = $request->input('mobile');
        $user->city         = $request->input('city');
        $user->state        = $request->input('state');
        $user->zip          = $request->input('zip');
        $user->address      = $request->input('address');

        $user->save();

        return redirect()->route('users.index')->with('success', _lang('Updated Sucessfully'));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant, $id) {
        $user = User::staff()->find($id);
        if ($user->tenant_owner == 1) {
            return back()->with('error', _lang('You can not delete tenant owner account'));
        }

        if ($user->id == auth()->id()) {
            return back()->with('error', _lang('You can not delete your own account'));
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', _lang('Deleted Sucessfully'));
    }
}