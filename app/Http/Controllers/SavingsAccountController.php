<?php
namespace App\Http\Controllers;

use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\Transaction;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Validator;

class SavingsAccountController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());

        $this->middleware(function ($request, $next) {
            $route_name = request()->route()->getName();
            if ($route_name == 'savings_accounts.store') {
                if (has_limit('savings_accounts', 'account_limit') <= 0) {
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
        return view('backend.admin.savings_accounts.list', compact('assets'));
    }

    public function get_table_data() {
        // Use DB query builder for complete control over column selection
        $savingsaccounts = DB::table('savings_accounts')
            ->select([
                'savings_accounts.id',
                'savings_accounts.account_number',
                'savings_accounts.member_id',
                'savings_accounts.savings_product_id',
                'savings_accounts.status as account_status',
                'savings_accounts.opening_balance',
                'savings_accounts.description',
                'savings_accounts.created_at',
                'savings_accounts.updated_at',
                'members.first_name as member_first_name',
                'members.last_name as member_last_name',
                'savings_products.name as savings_type_name',
                'currency.name as currency_name'
            ])
            ->leftJoin('members', 'savings_accounts.member_id', '=', 'members.id')
            ->leftJoin('savings_products', 'savings_accounts.savings_product_id', '=', 'savings_products.id')
            ->leftJoin('currency', 'savings_products.currency_id', '=', 'currency.id')
            ->where('savings_accounts.status', '!=', 0)
            ->orderBy("savings_accounts.id", "desc");

        return Datatables::of($savingsaccounts)
            ->editColumn('member_first_name', function ($savingsaccount) {
                return $savingsaccount->member_first_name . ' ' . $savingsaccount->member_last_name;
            })
            ->editColumn('account_status', function ($savingsaccount) {
                return status($savingsaccount->account_status);
            })
            ->filterColumn('member_first_name', function ($query, $keyword) {
                $query->where(function($q) use ($keyword) {
                    $q->where("members.first_name", "like", "{$keyword}%")
                      ->orWhere("members.last_name", "like", "{$keyword}%");
                });
            }, true)
            ->filterColumn('account_number', function ($query, $keyword) {
                $query->where('savings_accounts.account_number', 'like', "{$keyword}%");
            })
            ->filterColumn('savings_type_name', function ($query, $keyword) {
                $query->where('savings_products.name', 'like', "{$keyword}%");
            })
            ->filterColumn('account_status', function ($query, $keyword) {
                $query->where('savings_accounts.status', 'like', "{$keyword}%");
            })
            ->addColumn('action', function ($savingsaccount) {
                return '<div class="dropdown text-center">'
                . '<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action')
                . '&nbsp;</button>'
                . '<div class="dropdown-menu">'
                . '<a class="dropdown-item ajax-modal" href="' . route('savings_accounts.edit', $savingsaccount->id) . '" data-title="' . _lang('Account Details') . '"><i class="ti-pencil-alt"></i> ' . _lang('Edit') . '</a>'
                . '<a class="dropdown-item ajax-modal" href="' . route('savings_accounts.show', $savingsaccount->id) . '" data-title="' . _lang('Update Account') . '"><i class="ti-eye"></i>  ' . _lang('View') . '</a>'
                . '<form action="' . route('savings_accounts.destroy', $savingsaccount->id) . '" method="post">'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '<button class="dropdown-item btn-remove" type="submit"><i class="ti-trash"></i> ' . _lang('Delete') . '</button>'
                    . '</form>'
                    . '</div>'
                    . '</div>';
            })
            ->setRowId(function ($savingsaccount) {
                return "row_" . $savingsaccount->id;
            })
            ->rawColumns(['account_status', 'action'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.admin.savings_accounts.modal.create');
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
            'account_number'     => 'required|unique:savings_accounts|max:50',
            'member_id'          => 'required',
            'savings_product_id' => 'required',
            'status'             => 'required',
            'opening_balance'    => 'required|numeric',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('savings_accounts.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $accountType = SavingsProduct::find($request->savings_product_id);

        if ($request->opening_balance < $accountType->minimum_deposit_amount) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('You must deposit minimum') . ' ' . $accountType->minimum_deposit_amount . ' ' . $accountType->currency->name]);
            } else {
                return back()
                    ->with('error', _lang('You must deposit minimum') . ' ' . $accountType->minimum_deposit_amount . ' ' . $accountType->currency->name)
                    ->withInput();
            }
        }

        DB::beginTransaction();

        $savingsaccount                     = new SavingsAccount();
        $savingsaccount->account_number     = $accountType->account_number_prefix . $accountType->starting_account_number;
        $savingsaccount->member_id          = $request->input('member_id');
        $savingsaccount->savings_product_id = $request->input('savings_product_id');
        $savingsaccount->status             = $request->input('status');
        $savingsaccount->opening_balance    = $request->input('opening_balance');
        $savingsaccount->description        = $request->input('description');
        $savingsaccount->created_user_id    = auth()->id();

        $savingsaccount->save();

        //Increment account number
        $accountType->starting_account_number = $accountType->starting_account_number + 1;
        $accountType->save();

        //Create Transaction
        $transaction                     = new Transaction();
        $transaction->trans_date         = now();
        $transaction->member_id          = $savingsaccount->member_id;
        $transaction->savings_account_id = $savingsaccount->id;
        $transaction->amount             = $request->input('opening_balance');
        $transaction->dr_cr              = 'cr';
        $transaction->type               = 'Deposit';
        $transaction->method             = 'Manual';
        $transaction->status             = 2;
        $transaction->note               = $request->input('note');
        $transaction->description        = _lang('Initial Deposit');
        $transaction->created_user_id    = auth()->id();
        $transaction->branch_id          = auth()->user()->branch_id;

        $transaction->save();

        DB::commit();

        if ($savingsaccount->id > 0 && $transaction->id > 0) {
            if (! $request->ajax()) {
                return redirect()->route('savings_accounts.create')->with('success', _lang('Saved Successfully'));
            } else {
                return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $savingsaccount, 'table' => '#savings_accounts_table']);
            }
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $tenant, $id) {
        // SECURE: Add tenant validation for savings account access
        $savingsaccount = SavingsAccount::with('savings_type.currency', 'member')
                                        ->where('tenant_id', app('tenant')->id)
                                        ->where('id', $id)
                                        ->firstOrFail();
        if (! $request->ajax()) {
            return view('backend.admin.savings_accounts.view', compact('savingsaccount', 'id'));
        } else {
            return view('backend.admin.savings_accounts.modal.view', compact('savingsaccount', 'id'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $tenant, $id) {
        // SECURE: Add tenant validation for savings account operations
        $savingsaccount = SavingsAccount::where('tenant_id', app('tenant')->id)
                                        ->where('id', $id)
                                        ->firstOrFail();
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.admin.savings_accounts.modal.edit', compact('savingsaccount', 'id'));
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
            'account_number'     => [
                'required',
                Rule::unique('savings_accounts')->ignore($id),
            ],
            'member_id'          => 'required',
            'savings_product_id' => 'required',
            'status'             => 'required',
            'opening_balance'    => 'required|numeric',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('savings_accounts.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        // SECURE: Use proper authorization check instead of bypassing global scopes
        $savingsaccount = SavingsAccount::where('tenant_id', app('tenant')->id)
                                        ->where('id', $id)
                                        ->firstOrFail();
        $savingsaccount->account_number     = $request->input('account_number');
        $savingsaccount->member_id          = $request->input('member_id');
        $savingsaccount->savings_product_id = $request->input('savings_product_id');
        $savingsaccount->status             = $request->input('status');
        $savingsaccount->opening_balance    = $request->input('opening_balance');
        $savingsaccount->description        = $request->input('description');
        $savingsaccount->updated_user_id    = auth()->id();

        $savingsaccount->save();

        if (! $request->ajax()) {
            return redirect()->route('savings_accounts.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $savingsaccount, 'table' => '#savings_accounts_table']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant, $id) {
        // SECURE: Add tenant validation for savings account operations
        $savingsaccount = SavingsAccount::where('tenant_id', app('tenant')->id)
                                        ->where('id', $id)
                                        ->firstOrFail();
        $savingsaccount->delete();
        return redirect()->route('savings_accounts.index')->with('success', _lang('Deleted Successfully'));
    }

    public function get_account_by_member_id($tenant, $member_id) {
        $savingsaccounts = get_account_details($member_id);
        return response()->json(['accounts' => $savingsaccounts]);
    }
}