<?php
namespace App\Http\Controllers;

use App\Models\Guarantor;
use App\Models\GuarantorRequest;
use App\Models\Loan;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Mail\GuarantorInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class GuarantorController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());
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
            return view('backend.admin.guarantor.modal.create');
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $loan = Loan::find($request->loan_id);

        $validator = Validator::make($request->all(), [
            'loan_id'            => 'required',
            'savings_account_id' => 'required',
            'member_id'          => 'required|not_in:' . $loan->borrower_id,
            'amount'             => 'required|numeric',
        ], [
            'member_id.not_in' => $loan->borrower->name . ' ' . _lang('is the borrower of Loan') . ' (' . $loan->loan_id . ')',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('guarantors.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $account = SavingsAccount::find($request->savings_account_id);

        if ($account->savings_type->currency_id != $loan->currency_id) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Loan currency and account currency is mismatch')]);
            } else {
                return back()
                    ->with('error', _lang('Loan currency and account currency is mismatch'))
                    ->withInput();
            }
        }

        if ($request->amount > get_account_balance($account->id, $request->member_id)) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Insufficient account balance')]);
            } else {
                return back()
                    ->with('error', _lang('Insufficient account balance'))
                    ->withInput();
            }
        }

        $guarantor                     = new Guarantor();
        $guarantor->loan_id            = $request->input('loan_id');
        $guarantor->member_id          = $request->input('member_id');
        $guarantor->savings_account_id = $request->input('savings_account_id');
        $guarantor->amount             = $request->input('amount');

        $guarantor->save();

        //Prefix Output
        $guarantor->loan_id = $guarantor->loan->loan_id;

        if (! $request->ajax()) {
            return redirect()->route('guarantors.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $guarantor, 'table' => '#guarantors_table']);
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $tenant, $id) {
        $guarantor = Guarantor::find($id);
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.admin.guarantor.modal.edit', compact('guarantor', 'id'));
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
        $loan = Loan::find($request->loan_id);

        $validator = Validator::make($request->all(), [
            'loan_id'            => 'required',
            'member_id'          => 'required|not_in:' . $loan->borrower_id,
            'savings_account_id' => 'required',
            'amount'             => 'required|numeric',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('guarantors.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $account = SavingsAccount::find($request->savings_account_id);

        if ($account->savings_type->currency_id != $loan->currency_id) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Loan currency and account currency is mismatch')]);
            } else {
                return back()
                    ->with('error', _lang('Loan currency and account currency is mismatch'))
                    ->withInput();
            }
        }

        $guarantor      = Guarantor::find($id);
        $previousAmount = $request->member_id == $guarantor->member_id ? $guarantor->amount : 0;

        if ($request->amount > get_account_balance($account->id, $request->member_id) + $previousAmount) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Insufficient account balance')]);
            } else {
                return back()
                    ->with('error', _lang('Insufficient account balance'))
                    ->withInput();
            }
        }

        $guarantor->loan_id            = $request->input('loan_id');
        $guarantor->member_id          = $request->input('member_id');
        $guarantor->savings_account_id = $request->input('savings_account_id');
        $guarantor->amount             = $request->input('amount');

        $guarantor->save();

        //Prefix Output
        $guarantor->loan_id = $guarantor->loan->loan_id;

        if (! $request->ajax()) {
            return redirect()->route('guarantors.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $guarantor, 'table' => '#guarantors_table']);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($tenant, $id) {
        $guarantor = Guarantor::find($id);
        $guarantor->delete();
        return back()->with('success', _lang('Deleted Successfully'));
    }

    /**
     * Show guarantor invitation page
     */
    public function showInvitation($token) {
        $guarantorRequest = GuarantorRequest::where('token', $token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (!$guarantorRequest) {
            return view('guarantor.expired');
        }

        return view('guarantor.invitation', compact('guarantorRequest'));
    }

    /**
     * Accept guarantor invitation
     */
    public function accept(Request $request, $token) {
        $guarantorRequest = GuarantorRequest::where('token', $token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (!$guarantorRequest) {
            return view('guarantor.expired');
        }

        // Check if guarantor is a member of the same tenant
        $guarantor = Member::where('email', $guarantorRequest->guarantor_email)
            ->where('tenant_id', $guarantorRequest->tenant_id)
            ->first();

        if (!$guarantor) {
            return view('guarantor.not-member', compact('guarantorRequest'));
        }

        // Update guarantor request status
        $guarantorRequest->update([
            'status' => 'accepted',
            'responded_at' => now(),
            'response_message' => $request->response_message,
        ]);

        // Create guarantor record
        $guarantorRecord = new Guarantor();
        $guarantorRecord->loan_id = $guarantorRequest->loan_id;
        $guarantorRecord->member_id = $guarantor->id;
        $guarantorRecord->savings_account_id = $guarantor->savings_accounts()->first()->id ?? null;
        $guarantorRecord->amount = $guarantorRequest->loan->applied_amount; // Full loan amount as guarantee
        $guarantorRecord->save();

        return view('guarantor.accepted', compact('guarantorRequest'));
    }

    /**
     * Decline guarantor invitation
     */
    public function decline(Request $request, $token) {
        $guarantorRequest = GuarantorRequest::where('token', $token)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (!$guarantorRequest) {
            return view('guarantor.expired');
        }

        // Update guarantor request status
        $guarantorRequest->update([
            'status' => 'declined',
            'responded_at' => now(),
            'response_message' => $request->response_message,
        ]);

        return view('guarantor.declined', compact('guarantorRequest'));
    }
}