<?php

namespace App\Http\Controllers;

use App\Models\DepositRequest;
use App\Models\Transaction;
use App\Notifications\ApprovedDepositRequest;
use App\Notifications\RejectDepositRequest;
use DataTables;
use DB;
use Illuminate\Http\Request;

class DepositRequestController extends Controller {

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
        return view('backend.admin.deposit_request.list', compact('assets'));
    }

    public function get_table_data(Request $request) {

        $deposit_requests = DepositRequest::select('deposit_requests.*')
            ->with(['member', 'method', 'account.savings_type', 'account.savings_type.currency'])
            ->orderBy("deposit_requests.id", "desc");

        return Datatables::eloquent($deposit_requests)
            ->filter(function ($query) use ($request) {
                $status = $request->has('status') ? $request->status : 1;
                $query->where('status', $status);
            }, true)
            ->editColumn('member.first_name', function ($deposit_request) {
                return $deposit_request->member->first_name . ' ' . $deposit_request->member->last_name;
            })
            ->editColumn('amount', function ($deposit_request) {
                return decimalPlace($deposit_request->amount, currency($deposit_request->account->savings_type->currency->name));
            })
            ->editColumn('status', function ($deposit_request) {
                return transaction_status($deposit_request->status);
            })
            ->filterColumn('member.first_name', function ($query, $keyword) {
                $query->whereHas('member', function ($query) use ($keyword) {
                    return $query->where("first_name", "like", "{$keyword}%")
                        ->orWhere("last_name", "like", "{$keyword}%");
                });
            }, true)
            ->addColumn('action', function ($deposit_request) {
                $actions = '<div class="dropdown text-center">';
                $actions .= '<button class="btn btn-outline-primary btn-xs dropdown-toggle" type="button" id="dropdownMenuButton' . $deposit_request['id'] . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
                $actions .= _lang('Actions');
                $actions .= '</button>';
                $actions .= '<div class="dropdown-menu" aria-labelledby="dropdownMenuButton' . $deposit_request['id'] . '">';
            
                // Details Button
                $actions .= '<a href="' . route('deposit_requests.show', $deposit_request['id']) . '" class="dropdown-item"><i class="fas fa-eye mr-1"></i>' . _lang('Details') . '</a>';
            
                // Approve Button (if status is not 2)
                if ($deposit_request->status != 2) {
                    $actions .= '<a href="' . route('deposit_requests.approve', $deposit_request['id']) . '" class="dropdown-item"><i class="fas fa-check-circle text-success mr-1"></i>' . _lang('Approve') . '</a>';
                }
            
                // Reject Button (if status is not 1)
                if ($deposit_request->status != 1) {
                    $actions .= '<a href="' . route('deposit_requests.reject', $deposit_request['id']) . '" class="dropdown-item"><i class="fas fa-times-circle text-danger mr-1"></i>' . _lang('Reject') . '</a>';
                }
            
                // Divider and Delete Button
                $actions .= '<div class="dropdown-divider"></div>';
                $actions .= '<form action="' . route('deposit_requests.destroy', $deposit_request['id']) . '" method="post" class="d-inline">';
                $actions .= csrf_field();
                $actions .= '<input name="_method" type="hidden" value="DELETE">';
                $actions .= '<button class="dropdown-item text-danger btn-remove" type="submit"><i class="fas fa-trash-alt mr-1"></i>' . _lang('Delete') . '</button>';
                $actions .= '</form>';
            
                $actions .= '</div>';
                $actions .= '</div>';
            
                return $actions;
            })
            ->setRowId(function ($deposit_request) {
                return "row_" . $deposit_request->id;
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
        $depositrequest = DepositRequest::find($id);
        return view('backend.admin.deposit_request.view', compact('depositrequest', 'id'));
    }

    /**
     * Approve Wire Transfer
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approve($tenant, $id) {
        DB::beginTransaction();

        $depositRequest = DepositRequest::find($id);

        //Create Transaction
        $transaction                     = new Transaction();
        $transaction->trans_date         = now();
        $transaction->member_id          = $depositRequest->member_id;
        $transaction->savings_account_id = $depositRequest->credit_account_id;
        $transaction->charge             = convert_currency($depositRequest->method->currency->name, $depositRequest->account->savings_type->currency->name, $depositRequest->charge);
        $transaction->amount             = $depositRequest->amount;
        $transaction->dr_cr              = 'cr';
        $transaction->type               = 'Deposit';
        $transaction->method             = $depositRequest->method->name;
        $transaction->status             = 2;
        $transaction->description        = _lang('Deposit Via') . ' ' . $depositRequest->method->name;
        $transaction->created_user_id    = auth()->id();
        $transaction->branch_id          = auth()->user()->branch_id;

        $transaction->save();

        $depositRequest->status         = 2;
        $depositRequest->transaction_id = $transaction->id;
        $depositRequest->save();

        try {
            $transaction->member->notify(new ApprovedDepositRequest($transaction));
        } catch (\Exception $e) {}

        DB::commit();
        return redirect()->route('deposit_requests.index')->with('success', _lang('Request Approved'));
    }

    /**
     * Reject Wire Transfer
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reject($tenant, $id) {
        DB::beginTransaction();
        $depositRequest = DepositRequest::find($id);

        if ($depositRequest->transaction_id != null) {
            $transaction = Transaction::find($depositRequest->transaction_id);
            $transaction->delete();
        }

        $depositRequest->status         = 1;
        $depositRequest->transaction_id = null;
        $depositRequest->save();

        DB::commit();

        try {
            $depositRequest->member->notify(new RejectDepositRequest($depositRequest));
        } catch (\Exception $e) {}

        return redirect()->route('deposit_requests.index')->with('success', _lang('Request Rejected'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant, $id) {
        $depositRequest = DepositRequest::find($id);
        if ($depositRequest->transaction_id != null) {
            $transaction = Transaction::find($depositRequest->transaction_id);
            if($transaction){
                $transaction->delete();
            } 
        }
        $depositRequest->delete();
        return redirect()->route('deposit_requests.index')->with('success', _lang('Deleted Successfully'));
    }
}