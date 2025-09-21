<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Notifications\SubscriptionNotification;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

class SubscriptionPaymentController extends Controller {

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
        return view('backend.super_admin.subscription_payment.list', compact('assets'));
    }

    public function get_table_data() {
        $subscriptionpayments = SubscriptionPayment::select('subscription_payments.*')
            ->with('tenant', 'package', 'created_by')
            ->orderBy("subscription_payments.id", "desc");

        return Datatables::eloquent($subscriptionpayments)
            ->editColumn('status', function ($subscriptionpayment) {
                if ($subscriptionpayment->status == 0) {
                    return show_status(_lang('Pending'), 'warning');
                } else if ($subscriptionpayment->status == 1) {
                    return show_status(_lang('Completed'), 'success');
                } else if ($subscriptionpayment->status == 2) {
                    return show_status(_lang('Hold'), 'primary');
                } else if ($subscriptionpayment->status == 3) {
                    return show_status(_lang('Refund'), 'info');
                } else if ($subscriptionpayment->status == 4) {
                    return show_status(_lang('Cancelled'), 'danger');
                }
            })
            ->editColumn('amount', function ($subscriptionpayment) {
                return decimalPlace($subscriptionpayment->amount, currency_symbol());
            })
            ->addColumn('action', function ($subscriptionpayment) {
                return '<div class="text-center">
                    <a href="' . route('admin.subscription_payments.show', $subscriptionpayment['id']) . '" class="btn btn-outline-info btn-xs"><i class="far fa-eye mr-2"></i>' . _lang('Details') . '</a>
                    <a href="' . route('admin.subscription_payments.edit', $subscriptionpayment['id']) . '" class="btn btn-outline-primary btn-xs"><i class="ti-pencil mr-2"></i>' . _lang('Edit') . '</a>
                </div>';
            })
            ->setRowId(function ($subscriptionpayment) {
                return "row_" . $subscriptionpayment->id;
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        $alert_col = 'col-lg-8 offset-lg-2';
        return view('backend.super_admin.subscription_payment.create', compact('alert_col'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'tenant_id'      => 'required',
            'order_id'       => 'required|unique:subscription_payments',
            'payment_method' => 'required',
            'package_id'     => 'required',
            'amount'         => 'required|numeric',
            'status'         => 'required',
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.subscription_payments.create')
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        $subscriptionpayment                  = new SubscriptionPayment();
        $subscriptionpayment->tenant_id       = $request->input('tenant_id');
        $subscriptionpayment->order_id        = $request->input('order_id');
        $subscriptionpayment->payment_method  = $request->input('payment_method');
        $subscriptionpayment->package_id      = $request->input('package_id');
        $subscriptionpayment->amount          = $request->input('amount');
        $subscriptionpayment->status          = $request->input('status');
        $subscriptionpayment->created_user_id = auth()->id();

        $subscriptionpayment->save();

        $user                    = $subscriptionpayment->user;
        $user->membership_type   = 'member';
        $user->package_id        = $subscriptionpayment->package_id;
        $user->subscription_date = now();
        $user->valid_to          = update_membership_date($user->package, $tenant->getRawOriginal('subscription_date'));
        $user->save();

        DB::commit();

        event(new \App\Events\SubscriptionPayment($user, $subscriptionpayment));

        if ($subscriptionpayment->id > 0) {
            return redirect()->route('admin.subscription_payments.create')->with('success', _lang('Saved Successfully'));
        }

    }

    public function show(Request $request, $id) {
        $alert_col            = 'col-lg-8 offset-lg-2';
        $subscription_payment = SubscriptionPayment::find($id);
        return view('backend.super_admin.subscription_payment.view', compact('subscription_payment', 'id', 'alert_col'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $alert_col           = 'col-lg-8 offset-lg-2';
        $subscriptionpayment = SubscriptionPayment::find($id);
        return view('backend.super_admin.subscription_payment.edit', compact('subscriptionpayment', 'id', 'alert_col'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required',
            'amount'     => 'required|numeric',
            'status'     => 'required|in:0,1,2,3,4',
        ],[
            'status.in' => 'The selected status is invalid.'
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.subscription_payments.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $subscriptionpayment             = SubscriptionPayment::find($id);
        $subscriptionpayment->package_id = $request->input('package_id');
        $subscriptionpayment->amount     = $request->input('amount');
        $subscriptionpayment->status     = $request->input('status');

        $subscriptionpayment->save();

        if (! $request->ajax()) {
            return redirect()->route('admin.subscription_payments.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $subscriptionpayment, 'table' => '#subscription_payments_table']);
        }

    }

    public function approve_payment_requests($id) {
        $subscriptionPayment = SubscriptionPayment::find($id);
        if ($subscriptionPayment->status == 0) {
            $subscriptionPayment->status = 1;
            $subscriptionPayment->save();

            $tenant                    = $subscriptionPayment->tenant;
            $tenant->membership_type   = 'member';
            $tenant->subscription_date = now();
            $tenant->valid_to          = update_membership_date($subscriptionPayment->package, $tenant->getRawOriginal('subscription_date'));
            $tenant->s_email_send_at   = null;
            $tenant->save();

            $user = $tenant->owner;

            try {
                event(new \App\Events\SubscriptionPayment($user, $subscriptionPayment));
            } catch (\Exception $e) {
                //Nothing
            }

            try {
                $user->notify(new SubscriptionNotification($user));
            } catch (\Exception $e) {
                //Nothing
            }

            return back()->with('success', _lang('Payment request approved'));
        }
    }

    public function reject_payment_requests(Request $request, $id) {
        if ($request->isMethod('get')) {
            if (! $request->ajax()) {
                return back();
            }
            return view('backend.super_admin.subscription_payment.modal.reject_payment_request', compact('id'));
        } else {
            $validator = Validator::make($request->all(), [
                'reason' => 'required',
            ]);

            if ($validator->fails()) {
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
                } else {
                    return back()->withErrors($validator)->withInput();
                }
            }

            $subscriptionPayment = SubscriptionPayment::find($id);
            $data = $subscriptionPayment->extra;
            $data[] = (object)[
                'field_label' => 'Reject Reason',
                'field_name' => 'reject_reason',
                'field_type' => 'textarea',
                'field_value' => $request->reason,
            ];

            if ($subscriptionPayment->status == 0) {
                $subscriptionPayment->status        = 4;
                $subscriptionPayment->extra         = json_encode($data);
                $subscriptionPayment->save();

                return back()->with('success', _lang('Payment request rejected'));
            }
        }
    }
}