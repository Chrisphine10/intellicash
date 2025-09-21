<?php
namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\WithdrawRequest;
use App\Notifications\ApprovedWithdrawRequest;
use App\Notifications\RejectWithdrawRequest;
use DataTables;
use DB;
use Illuminate\Http\Request;

class WithdrawRequestController extends Controller {

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
        return view('backend.admin.withdraw_request.list', compact('assets'));
    }

    public function get_table_data(Request $request) {

        $withdraw_requests = WithdrawRequest::select('withdraw_requests.*')
            ->with(['member', 'method', 'account.savings_type', 'account.savings_type.currency'])
            ->orderBy("withdraw_requests.id", "desc");

        return Datatables::eloquent($withdraw_requests)
            ->filter(function ($query) use ($request) {
                $status = $request->has('status') ? $request->status : 1;
                $query->where('status', $status);
            }, true)
            ->editColumn('member.first_name', function ($deposit_request) {
                return $deposit_request->member->first_name . ' ' . $deposit_request->member->last_name;
            })
            ->editColumn('amount', function ($withdraw_request) {
                return decimalPlace($withdraw_request->amount, currency($withdraw_request->method->currency->name));
            })
            ->editColumn('status', function ($withdraw_request) {
                return transaction_status($withdraw_request->status);
            })
            ->filterColumn('member.first_name', function ($query, $keyword) {
                $query->whereHas('member', function ($query) use ($keyword) {
                    return $query->where("first_name", "like", "{$keyword}%")
                        ->orWhere("last_name", "like", "{$keyword}%");
                });
            }, true)
            ->addColumn('action', function ($withdraw_request) {
                $actions = '<div class="dropdown text-center">';
                $actions .= '<button class="btn btn-outline-primary btn-xs dropdown-toggle" type="button" id="dropdownMenuButton' . $withdraw_request['id'] . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
                $actions .= _lang('Actions');
                $actions .= '</button>';
                $actions .= '<div class="dropdown-menu" aria-labelledby="dropdownMenuButton' . $withdraw_request['id'] . '">';

                $actions .= '<a href="' . route('withdraw_requests.show', $withdraw_request['id']) . '" class="dropdown-item"><i class="fas fa-eye mr-1"></i>' . _lang('Details') . '</a>';

                if ($withdraw_request->status != 2) {
                    $actions .= '<a href="' . route('withdraw_requests.approve', $withdraw_request['id']) . '" class="dropdown-item"><i class="fas fa-check-circle text-success mr-1"></i>' . _lang('Approve') . '</a>';
                }
                if ($withdraw_request->status != 1) {
                    $actions .= '<a href="' . route('withdraw_requests.reject', $withdraw_request['id']) . '" class="dropdown-item"><i class="fas fa-times-circle text-danger mr-1"></i>' . _lang('Reject') . '</a>';
                }

                $actions .= '<div class="dropdown-divider"></div>';
                $actions .= '<form action="' . route('withdraw_requests.destroy', $withdraw_request['id']) . '" method="post" class="d-inline">';
                $actions .= csrf_field();
                $actions .= '<input name="_method" type="hidden" value="DELETE">';
                $actions .= '<button class="dropdown-item text-danger btn-remove" type="submit"><i class="fas fa-trash-alt mr-1"></i>' . _lang('Delete') . '</button>';
                $actions .= '</form>';

                $actions .= '</div>';
                $actions .= '</div>';

                return $actions;
            })
            ->setRowId(function ($withdraw_request) {
                return "row_" . $withdraw_request->id;
            })
            ->rawColumns(['user.name', 'status', 'action'])
            ->make(true);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $tenant, $id) {
        $withdrawRequest = WithdrawRequest::find($id);
        return view('backend.admin.withdraw_request.view', compact('withdrawRequest', 'id'));
    }

    /**
     * Approve Wire Transfer
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approve($tenant, $id) {
        DB::beginTransaction();

        $withdrawRequest         = WithdrawRequest::find($id);
        $withdrawRequest->status = 2;
        $withdrawRequest->save();

        $transaction         = Transaction::find($withdrawRequest->transaction_id);
        $transaction->status = 2;
        $transaction->save();

        $childTransaction = Transaction::where('parent_id', $transaction->id)->first();

        if ($childTransaction) {
            $childTransaction->status = 2;
            $childTransaction->save();
        }

        try {
            $transaction->member->notify(new ApprovedWithdrawRequest($withdrawRequest));
        } catch (\Exception $e) {}

        DB::commit();
        return redirect()->route('withdraw_requests.index')->with('success', _lang('Request Approved'));
    }

    /**
     * Reject Wire Transfer
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reject($tenant, $id) {
        DB::beginTransaction();
        $withdrawRequest = WithdrawRequest::find($id);

        $transaction         = Transaction::find($withdrawRequest->transaction_id);
        $transaction->status = 1;
        $transaction->save();

        $childTransaction = Transaction::where('parent_id', $transaction->id)->first();

        if ($childTransaction) {
            $childTransaction->status = 1;
            $childTransaction->save();
        }

        $withdrawRequest->status = 1;
        $withdrawRequest->save();

        try {
            $transaction->member->notify(new RejectWithdrawRequest($withdrawRequest));
        } catch (\Exception $e) {}

        DB::commit();
        return redirect()->route('withdraw_requests.index')->with('success', _lang('Request Rejected'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant, $id) {
        $withdrawRequest = WithdrawRequest::find($id);
        if ($withdrawRequest->transaction_id != null) {
            $transaction = Transaction::find($withdrawRequest->transaction_id);
            if ($transaction) {
                $transaction->delete();
            }
        }
        $withdrawRequest->delete();
        return redirect()->route('withdraw_requests.index')->with('success', _lang('Deleted Successfully'));
    }
}
