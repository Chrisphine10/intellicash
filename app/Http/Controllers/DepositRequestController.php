<?php

namespace App\Http\Controllers;

use App\Models\DepositRequest;
use App\Models\Transaction;
use App\Notifications\ApprovedDepositRequest;
use App\Notifications\RejectDepositRequest;
use DataTables;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
        // Ensure tenant isolation
        $tenant = app('tenant');
        
        $deposit_requests = DepositRequest::select([
                'deposit_requests.*',
                'members.first_name as member_first_name',
                'members.last_name as member_last_name',
                'savings_accounts.account_number',
                'savings_products.name as savings_type_name',
                'currency.name as currency_name',
                'deposit_methods.name as method_name'
            ])
            ->leftJoin('members', 'deposit_requests.member_id', '=', 'members.id')
            ->leftJoin('savings_accounts', 'deposit_requests.savings_account_id', '=', 'savings_accounts.id')
            ->leftJoin('savings_products', 'savings_accounts.savings_product_id', '=', 'savings_products.id')
            ->leftJoin('currency', 'savings_products.currency_id', '=', 'currency.id')
            ->leftJoin('deposit_methods', 'deposit_requests.deposit_method_id', '=', 'deposit_methods.id')
            ->where('deposit_requests.tenant_id', $tenant->id) // Tenant isolation
            ->where('deposit_requests.status', $request->input('status', 1)) // Status filtering
            ->orderBy("deposit_requests.id", "desc");

        return Datatables::eloquent($deposit_requests)
            ->editColumn('method_name', function ($deposit_request) {
                return $deposit_request->method_name ?? 'N/A';
            })
            ->editColumn('member_first_name', function ($deposit_request) {
                return $deposit_request->member_first_name . ' ' . $deposit_request->member_last_name;
            })
            ->editColumn('amount', function ($deposit_request) {
                return decimalPlace($deposit_request->amount, currency($deposit_request->currency_name));
            })
            ->editColumn('status', function ($deposit_request) {
                return transaction_status($deposit_request->status);
            })
            ->filterColumn('member_first_name', function ($query, $keyword) {
                $query->where(function($q) use ($keyword) {
                    $q->where("members.first_name", "like", "{$keyword}%")
                      ->orWhere("members.last_name", "like", "{$keyword}%");
                });
            }, true)
            ->addColumn('action', function ($deposit_request) use ($tenant) {
                $actions = '<div class="dropdown text-center">';
                $actions .= '<button class="btn btn-outline-primary btn-xs dropdown-toggle" type="button" id="dropdownMenuButton' . $deposit_request['id'] . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
                $actions .= _lang('Actions');
                $actions .= '</button>';
                $actions .= '<div class="dropdown-menu" aria-labelledby="dropdownMenuButton' . $deposit_request['id'] . '">';
            
                // Details Button
                $actions .= '<a href="' . route('deposit_requests.show', ['tenant' => $tenant->slug, 'id' => $deposit_request['id']]) . '" class="dropdown-item"><i class="fas fa-eye mr-1"></i>' . _lang('Details') . '</a>';
            
                // Approve Button (if status is not 2)
                if ($deposit_request->status != 2) {
                    $actions .= '<a href="' . route('deposit_requests.approve', ['tenant' => $tenant->slug, 'id' => $deposit_request['id']]) . '" class="dropdown-item"><i class="fas fa-check-circle text-success mr-1"></i>' . _lang('Approve') . '</a>';
                }
            
                // Reject Button (if status is not 1)
                if ($deposit_request->status != 1) {
                    $actions .= '<a href="' . route('deposit_requests.reject', ['tenant' => $tenant->slug, 'id' => $deposit_request['id']]) . '" class="dropdown-item"><i class="fas fa-times-circle text-danger mr-1"></i>' . _lang('Reject') . '</a>';
                }
            
                // Divider and Delete Button
                $actions .= '<div class="dropdown-divider"></div>';
                $actions .= '<form action="' . route('deposit_requests.destroy', ['tenant' => $tenant->slug, 'id' => $deposit_request['id']]) . '" method="post" class="d-inline">';
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
        $tenant = app('tenant');
        
        $depositrequest = DepositRequest::where('id', $id)
            ->where('tenant_id', $tenant->id) // Tenant isolation
            ->firstOrFail();
            
        return view('backend.admin.deposit_request.view', compact('depositrequest', 'id'));
    }

    /**
     * Approve Deposit Request
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function approve($tenant, $id) {
        $tenant = app('tenant');
        
        DB::beginTransaction();
        
        try {
            $depositRequest = DepositRequest::where('id', $id)
                ->where('tenant_id', $tenant->id) // Tenant isolation
                ->firstOrFail();

            // Validate that the request is in pending status
            if ($depositRequest->status != 0) {
                throw new \Exception('Only pending deposit requests can be approved');
            }

            //Create Transaction
            $transaction = new Transaction();
            $transaction->trans_date = now();
            $transaction->member_id = $depositRequest->member_id;
            $transaction->savings_account_id = $depositRequest->savings_account_id;
            $transaction->charge = convert_currency($depositRequest->method->currency->name, $depositRequest->account->savings_type->currency->name, $depositRequest->charge);
            $transaction->amount = $depositRequest->amount;
            $transaction->dr_cr = 'cr';
            $transaction->type = 'Deposit';
            $transaction->method = $depositRequest->method->name;
            $transaction->status = 2;
            $transaction->description = _lang('Deposit Via') . ' ' . $depositRequest->method->name;
            $transaction->created_user_id = auth()->id();
            $transaction->branch_id = auth()->user()->branch_id;
            $transaction->tenant_id = $tenant->id; // Tenant isolation

            $transaction->save();

            $depositRequest->status = 2;
            $depositRequest->transaction_id = $transaction->id;
            $depositRequest->save();

            try {
                $transaction->member->notify(new ApprovedDepositRequest($transaction));
            } catch (\Exception $e) {
                // Log notification error but don't fail the transaction
                \Log::warning('Failed to send deposit approval notification: ' . $e->getMessage());
            }

            DB::commit();
            return redirect()->route('deposit_requests.index')->with('success', _lang('Request Approved'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->route('deposit_requests.index')->with('error', _lang('Failed to approve request: ') . $e->getMessage());
        }
    }

    /**
     * Reject Deposit Request
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reject($tenant, $id) {
        $tenant = app('tenant');
        
        DB::beginTransaction();
        
        try {
            $depositRequest = DepositRequest::where('id', $id)
                ->where('tenant_id', $tenant->id) // Tenant isolation
                ->firstOrFail();

            if ($depositRequest->transaction_id != null) {
                $transaction = Transaction::find($depositRequest->transaction_id);
                if ($transaction) {
                    $transaction->delete();
                }
            }

            $depositRequest->status = 1;
            $depositRequest->transaction_id = null;
            $depositRequest->save();

            DB::commit();

            try {
                $depositRequest->member->notify(new RejectDepositRequest($depositRequest));
            } catch (\Exception $e) {
                // Log notification error but don't fail the transaction
                \Log::warning('Failed to send deposit rejection notification: ' . $e->getMessage());
            }

            return redirect()->route('deposit_requests.index')->with('success', _lang('Request Rejected'));
            
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->route('deposit_requests.index')->with('error', _lang('Failed to reject request: ') . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant, $id) {
        $tenant = app('tenant');
        
        try {
            $depositRequest = DepositRequest::where('id', $id)
                ->where('tenant_id', $tenant->id) // Tenant isolation
                ->firstOrFail();
                
            if ($depositRequest->transaction_id != null) {
                $transaction = Transaction::find($depositRequest->transaction_id);
                if ($transaction) {
                    $transaction->delete();
                }
            }
            
            $depositRequest->delete();
            return redirect()->route('deposit_requests.index')->with('success', _lang('Deleted Successfully'));
            
        } catch (\Exception $e) {
            return redirect()->route('deposit_requests.index')->with('error', _lang('Failed to delete request: ') . $e->getMessage());
        }
    }
}