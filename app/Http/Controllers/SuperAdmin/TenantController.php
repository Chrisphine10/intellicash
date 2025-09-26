<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Utilities\Overrider;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Validator;

class TenantController extends Controller {

    private $alert_col = 'col-lg-8 offset-lg-2';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $assets = ['datatable'];
        return view('backend.super_admin.tenant.list', compact('assets'));
    }

    public function get_table_data() {
        $tenants = Tenant::with('package')
            ->select('tenants.*')
            ->orderBy("tenants.id", "desc");

        return Datatables::eloquent($tenants)
            ->editColumn('package.name', function ($tenant) {
                return $tenant->package->name != null ? $tenant->package->name . ' (' . ucwords($tenant->package->package_type) . ')' : '';
            })
            ->editColumn('membership_type', function ($tenant) {
                if ($tenant->membership_type == 'member') {
                    return show_status(ucwords($tenant->membership_type), 'success');
                } else {
                    return show_status(ucwords($tenant->membership_type), 'danger');
                }
            })
            ->editColumn('status', function ($tenant) {
                return '<div class="dropdown text-center">' . status($tenant->status) . '</div>';
            })
            ->addColumn('action', function ($tenant) {
                return '<div class="dropdown text-center">'
                . '<button class="btn btn-outline-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action')
                . '</button>'
                . '<div class="dropdown-menu">'
                . '<a class="dropdown-item" href="' . route('admin.tenants.edit', $tenant['id']) . '"><i class="fas fa-pencil-alt"></i> ' . _lang('Edit') . '</a>'
                . '<a class="dropdown-item" href="' . route('admin.tenants.show', $tenant['id']) . '"><i class="fas fa-eye"></i> ' . _lang('Details') . '</a>'
                . '<form action="' . route('admin.tenants.destroy', $tenant['id']) . '" method="post">'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '<button class="dropdown-item btn-remove" type="submit"><i class="fas fa-trash-alt"></i> ' . _lang('Delete') . '</button>'
                    . '</form>'
                    . '</div>'
                    . '</div>';
            })
            ->setRowId(function ($tenant) {
                return "row_" . $tenant->id;
            })
            ->rawColumns(['membership_type', 'status', 'action'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        $alert_col = $this->alert_col;
        return view('backend.super_admin.tenant.create', compact('alert_col'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'workspace'       => 'required|unique:tenants,slug|alpha_dash|max:30',
            'name'            => 'required',
            'membership_type' => 'required',
            'package_id'      => 'required',
            'status'          => 'required',
            'email'           => [
                'required',
                'email',
                Rule::unique('users')->where(function ($query) {
                    return $query->where('tenant_owner', 1)
                        ->orWhere('user_type', 'superadmin');
                }),
            ],
            'password'        => 'required|min:6',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.tenants.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        DB::beginTransaction();

        $tenant                    = new Tenant();
        $tenant->slug              = $request->input('workspace');
        $tenant->name              = $request->input('name');
        $tenant->membership_type   = $request->input('membership_type');
        $tenant->package_id        = $request->input('package_id');
        $tenant->subscription_date = now();
        $tenant->valid_to          = update_membership_date($tenant->package, $tenant->subscription_date);
        $tenant->status            = $request->input('status');

        $tenant->save();

        // Get the Admin role for this tenant
        $adminRole = DB::table('roles')
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Admin')
            ->first();

        $user                  = new User();
        $user->name            = $request->input('name');
        $user->email           = $request->input('email');
        $user->user_type       = 'admin';
        $user->tenant_id       = $tenant->id;
        $user->tenant_owner    = 1;
        $user->status          = 1;
        $user->profile_picture = 'default.png';
        $user->password        = Hash::make($request->password);
        $user->role_id         = $adminRole ? $adminRole->id : null;
        $user->save();

        DB::commit();

        if (! $request->ajax()) {
            return redirect()->route('admin.tenants.show', $tenant->id)->with('success', _lang('New tenant created successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('New tenant created successfully'), 'data' => $tenant, 'table' => '#tenants_table']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id) {
        $assets    = ['tinymce'];
        $alert_col = 'col-lg-8 offset-lg-2';
        $tenant    = Tenant::find($id);
        return view('backend.super_admin.tenant.view', compact('tenant', 'id', 'assets', 'alert_col'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $alert_col = $this->alert_col;
        $tenant    = Tenant::find($id);
        return view('backend.super_admin.tenant.edit', compact('tenant', 'id', 'alert_col'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        $tenant    = Tenant::find($id);
        $validator = Validator::make($request->all(), [
            'workspace'       => [
                'required',
                'alpha_dash',
                'max:30',
                Rule::unique('tenants', 'slug')->ignore($id),
            ],
            'name'            => 'required',
            'email'           => [
                'required',
                'email',
                Rule::unique('users')->where(function ($query) {
                    return $query->where('tenant_owner', 1)
                        ->orWhere('user_type', 'superadmin');
                })->ignore($tenant->owner->id),
            ],
            'password'        => 'nullable|min:6',
            'membership_type' => 'required',
            'package_id'      => 'required',
            'status'          => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.tenants.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        DB::beginTransaction();

        $tenant                  = Tenant::find($id);
        $tenant->slug            = $request->input('workspace');
        $tenant->name            = $request->input('name');
        $tenant->membership_type = $request->input('membership_type');
        $tenant->package_id      = $request->input('package_id');
        $tenant->valid_to        = $request->input('valid_to');
        $tenant->status          = $request->input('status');

        $tenant->save();

        $user = User::find($tenant->owner->id);
        if ($request->password != null) {
            $user->password = Hash::make($request->password);
        }
        $user->name  = $request->input('name');
        $user->email = $request->input('email');
        $user->save();

        DB::commit();

        if (! $request->ajax()) {
            return redirect()->route('admin.tenants.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $tenant, 'table' => '#tenants_table']);
        }
    }

    public function checkSlug(Request $request, $ignoreId = null) {
        $this->validate($request, [
            'workspace' => 'required|max:30|regex:/^[a-zA-Z][a-zA-Z0-9_-]*$/',
        ], [
            'workspace.regex' => _lang('The workspace start with letter and may only contain letters, numbers, dashes and underscores.'),
        ]);
        $exists = Tenant::where('slug', $request->workspace)
            ->when($ignoreId, function ($query) use ($ignoreId) {
                return $query->where('id', '!=', $ignoreId);
            })
            ->exists();
        return response()->json(['exists' => $exists, 'message' => $exists ? $request->workspace . ' ' . _lang('is not available') : $request->workspace . ' ' . _lang('is available')]);
    }

    public function send_email(Request $request) {
        $this->validate($request, [
            'tenant_id' => 'required|exists:tenants,id',
            'subject'   => 'required',
            'message'   => 'required',
        ]);

        $tenant = Tenant::find($request->tenant_id);
        if ($tenant->owner->email != null) {
            Overrider::load('Settings');

            $data = [
                'subject' => $request->subject,
                'message' => $request->message,
            ];

            $mail          = new \stdClass();
            $mail->subject = $data['subject'];
            $mail->to      = $tenant->owner->email;
            $mail->name    = $tenant->owner->name;
            $mail->message = $data['message'];

            try {
                Mail::to($mail->to)->send(new \App\Mail\TenantMail($mail));
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage())->withInput();
            }

            return back()->with('success', _lang('Email Sent Successfully'));
        } else {
            return back()->with('error', _lang('Email Not Found'))->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $tenant = Tenant::find($id);
        $tenant->delete();
        return redirect()->route('admin.tenants.index')->with('success', _lang('Deleted Successfully'));
    }
}
