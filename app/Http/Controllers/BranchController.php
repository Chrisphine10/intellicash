<?php
namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BranchController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());

        $this->middleware(function ($request, $next) {
            $route_name = request()->route()->getName();
            if ($route_name == 'branches.store') {
                if (has_limit('branches', 'branch_limit') <= 0) {
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
        $assets  = ['datatable'];
        $branches = Branch::select('id', 'name', 'contact_email', 'contact_phone')
            ->withCount('members')
            ->orderBy('id', 'desc')
            ->paginate(15);
        return view('backend.admin.branch.list', compact('branches', 'assets'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        if (! $request->ajax()) {
            $alert_col = 'col-lg-8 offset-lg-2';
            return view('backend.admin.branch.create', compact('alert_col'));
        } else {
            return view('backend.admin.branch.modal.create');
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'address'       => 'nullable|string|max:1000',
            'descriptions'  => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('branches.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $branch                = new Branch();
        $branch->name          = $request->input('name');
        $branch->contact_email = $request->input('contact_email');
        $branch->contact_phone = $request->input('contact_phone');
        $branch->address       = $request->input('address');
        $branch->descriptions  = $request->input('descriptions');

        $branch->save();

        if (! $request->ajax()) {
            return redirect()->route('branches.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $branch, 'table' => '#branches_table']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $tenant, $id) {
        $branch = Branch::find($id);
        if (! $request->ajax()) {
            $alert_col = 'col-lg-8 offset-lg-2';
            return view('backend.admin.branch.view', compact('branch', 'id', 'alert_col'));
        } else {
            return view('backend.admin.branch.modal.view', compact('branch', 'id'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $tenant, $id) {
        $branch = Branch::findOrFail($id);
        if (! $request->ajax()) {
            $alert_col = 'col-lg-8 offset-lg-2';
            return view('backend.admin.branch.edit', compact('branch', 'id', 'alert_col'));
        } else {
            return view('backend.admin.branch.modal.edit', compact('branch', 'id'));
        }
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
            'name'          => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'address'       => 'nullable|string|max:1000',
            'descriptions'  => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('branches.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $branch                = Branch::find($id);
        $branch->name          = $request->input('name');
        $branch->contact_email = $request->input('contact_email');
        $branch->contact_phone = $request->input('contact_phone');
        $branch->address       = $request->input('address');
        $branch->descriptions  = $request->input('descriptions');

        $branch->save();

        if (! $request->ajax()) {
            return redirect()->route('branches.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $branch, 'table' => '#branches_table']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant, $id) {
        try {
            $branch = Branch::findOrFail($id);
            
            // Check if branch can be deleted
            if (!$branch->canBeDeleted()) {
                return redirect()->route('branches.index')
                    ->with('error', _lang('Cannot delete branch with existing members'));
            }
            
            $branch->delete();
            
            return redirect()->route('branches.index')
                ->with('success', _lang('Deleted Successfully'));
                
        } catch (\Exception $e) {
            return redirect()->route('branches.index')
                ->with('error', _lang('An error occurred while deleting the branch'));
        }
    }

    /**
     * Switch to a different branch
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function switchBranch(Request $request) {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
        }

        $branch = Branch::findOrFail($request->branch_id);
        
        // Check if user has permission to switch to this branch
        $user = auth()->user();
        if ($user->user_type != 'admin' && $user->all_branch_access != 1) {
            return response()->json(['result' => 'error', 'message' => _lang('Unauthorized branch access')]);
        }
        
        session(['branch' => $branch->name, 'branch_id' => $branch->id]);
        
        return response()->json(['result' => 'success', 'message' => _lang('Branch switched successfully')]);
    }
}