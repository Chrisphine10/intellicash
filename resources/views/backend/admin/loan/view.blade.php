@extends('layouts.app')

@section('content')
<style>
.badge {
    font-size: 0.75em;
    padding: 0.375rem 0.75rem;
}
.badge-info {
    background-color: #17a2b8;
}
.badge-success {
    background-color: #28a745;
}
.badge-warning {
    background-color: #ffc107;
    color: #212529;
}
.badge-danger {
    background-color: #dc3545;
}
.badge-secondary {
    background-color: #6c757d;
}
.table-info {
    background-color: #d1ecf1;
}
</style>
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-sm-flex align-items-center justify-content-between">
                <div class="panel-title">{{ _lang('View Loan Details') }}</div>
                @if($loan->status == 0)
                <div>
                <a class="btn btn-primary btn-xs" href="{{ route('loans.approve', $loan['id']) }}">
                    <i class="fas fa-check-circle mr-1"></i>{{ _lang('Click to Approve') }}</a>
                <a class="btn btn-danger btn-xs confirm-alert" data-message="{{ _lang('Are you sure you want to reject this loan application?') }}" href="{{ route('loans.reject', $loan['id']) }}">
                    <i class="fas fa-times-circle mr-1"></i>{{ _lang('Click to Reject') }}
                </a>
                </div>
                @endif
            </div>
            <div class="card-body">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#loan_details">{{ _lang("Loan Details") }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#guarantor">{{ _lang("Guarantor") }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#collateral">{{ _lang("Collateral") }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#schedule">{{ _lang("Repayments Schedule") }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#repayments">{{ _lang("Repayments") }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('loans.edit', $loan['id']) }}">{{ _lang("Edit") }}</a>
                    </li>
                </ul>
                <!-- Tab panes -->
                <div class="tab-content">
                    <div class="tab-pane active" id="loan_details">
                        @if($loan->status == 0)
                        <div class="alert alert-warning mt-4">
                            <span>
                            {{ _lang("Add Loan ID, Release Date and First Payment Date before approving loan request") }}
                            </span>
                        </div>
                        @endif
                        <table class="table table-bordered mt-4">
                            <tr>
                                <td>{{ _lang("Loan ID") }}</td>
                                <td>{{ $loan->loan_id }}</td>
                            </tr>
                            <tr>
                                <td>{{ _lang("Loan Type") }}</td>
                                <td>{{ $loan->loan_product->name }}</td>
                            </tr>
                            <tr>
                                <td>{{ _lang("Borrower") }}</td>
                                <td>{{ $loan->borrower->first_name.' '.$loan->borrower->last_name }}</td>
                            </tr>
                            <tr>
                                <td>{{ _lang("Member No") }}</td>
                                <td>{{ $loan->borrower->member_no }}</td>
                            </tr>
                            <tr>
                                <td>{{ _lang("Status") }}</td>
                                <td>
                                @if($loan->status == 0)
                                {!! xss_clean(show_status(_lang('Pending'), 'warning')) !!}
                                @elseif($loan->status == 1)
                                {!! xss_clean(show_status(_lang('Approved'), 'success')) !!}
                                @elseif($loan->status == 2)
                                {!! xss_clean(show_status(_lang('Completed'), 'info')) !!}
                                @elseif($loan->status == 3)
                                {!! xss_clean(show_status(_lang('Cancelled'), 'danger')) !!}
                                @endif
                                </td>
                            </tr>
                            <tr>
                                <td>{{ _lang("First Payment Date") }}</td>
                                <td>{{ $loan->first_payment_date }}</td>
                            </tr>
                            <tr>
                                <td>{{ _lang("Release Date") }}</td>
                                <td>
                                {{ $loan->release_date != '' ? $loan->release_date : '' }}
                                </td>
                            </tr>
                            <tr>
                                <td>{{ _lang("Applied Amount") }}</td>
                                <td>
                                {{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}
                                </td>
                            </tr>
                            <tr>
                                <td>{{ _lang("Total Principal Paid") }}</td>
                                <td class="text-success">
                                {{ decimalPlace($loan->total_paid, currency($loan->currency->name)) }}
                                </td>
                            </tr>
                            <tr>
                                <td>{{ _lang("Total Interest Paid") }}</td>
                                <td class="text-success">
                                {{ decimalPlace($loan->payments->sum('interest'), currency($loan->currency->name)) }}
                                </td>
                            </tr>
                            <tr>
                                <td>{{ _lang("Total Penalties Paid") }}</td>
                                <td class="text-success">
                                {{ decimalPlace($loan->payments->sum('late_penalties'), currency($loan->currency->name)) }}
                                </td>
                            </tr>
                            <tr>
                                <td>{{ _lang("Due Amount") }}</td>
                                <td class="text-danger">
                                {{ decimalPlace($loan->applied_amount - $loan->total_paid, currency($loan->currency->name)) }}
                                </td>
                            </tr>
                            <tr>
                                <td>{{ _lang("Late Payment Penalties") }}</td>
                                <td>{{ $loan->late_payment_penalties }} %</td>
                            </tr>
                            <!--Custom Fields-->
                            @if(! $customFields->isEmpty())
                                @php $customFieldsData = json_decode($loan->custom_fields, true); @endphp
                                @foreach($customFields as $customField)
                                <tr>
                                <td>{{ $customField->field_name }}</td>
                                <td>
                                        @if($customField->field_type == 'file')
                                        @php $file = $customFieldsData[$customField->field_name]['field_value'] ?? null; @endphp
                                        {!! $file != null ? '<a href="'. asset('public/uploads/media/'.$file) .'" target="_blank" class="btn btn-xs btn-outline-primary"><i class="far fa-eye mr-2"></i>'._lang('Preview').'</a>' : '' !!}
                                        @else
                                        {{ $customFieldsData[$customField->field_name]['field_value'] ?? null }}
                                        @endif
                                </td>
                                </tr>
                                @endforeach
                            @endif

                            @if($loan->status == 1)
                            <tr>
                                <td>{{ _lang("Disburse Method") }}</td>
                                <td>{{ $loan->disburse_method == 'cash' ? ucwords($loan->disburse_method) : _lang('Transfer to Account') }}</td>
                            </tr>

                            @if($loan->disburse_method == 'account')
                            <tr>
                                <td>{{ _lang("Disburse Account Details") }}</td>
                                @if($loan->disburseTransaction)
                                <td>{{ $loan->disburseTransaction->account->account_number }} ({{ $loan->disburseTransaction->account->savings_type->name }} - {{ $loan->disburseTransaction->account->savings_type->currency->name }})</td>
                                @else
                                <td>{{ _lang("No Account Found") }}</td>
                                @endif
                            </tr>
                            @endif

                            <tr>
                                <td>{{ _lang("Approved Date") }}</td>
                                <td>{{ $loan->approved_date }}</td>
                            </tr>
                            <tr>
                                <td>{{ _lang("Approved By") }}</td>
                                <td>{{ $loan->approved_by->name }}</td>
                            </tr>
                            @endif

                            <tr>
                                <td>{{ _lang("Created By") }}</td>
                                <td>{{ $loan->created_by->name }}</td>
                            </tr>

                            <tr>
                                <td>{{ _lang("Attachment") }}</td>
                                <td>
                                {!! $loan->attachment == "" ? '' : '<a href="'. asset('public/uploads/media/'.$loan->attachment) .'" target="_blank">'._lang('Download').'</a>' !!}
                                </td>
                            </tr>
                            
                            <tr>
                                <td>{{ _lang("Description") }}</td>
                                <td>{{ $loan->description }}</td>
                            </tr>
                            <tr>
                                <td>{{ _lang("Remarks") }}</td>
                                <td>{{ $loan->remarks }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="tab-pane fade mt-4" id="guarantor">
                        <!-- Guarantor Requests Section -->
                        @if(isset($guarantorRequests) && $guarantorRequests->count() > 0)
                        <div class="card mb-4">
                            <div class="card-header border">
                                <h5 class="mb-0"><i class="fas fa-user-friends"></i> {{ _lang('Guarantor Requests') }}</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th class="pl-4">{{ _lang('Guarantor Name') }}</th>
                                                <th>{{ _lang('Email') }}</th>
                                                <th>{{ _lang('Status') }}</th>
                                                <th>{{ _lang('Requested Date') }}</th>
                                                <th>{{ _lang('Response Date') }}</th>
                                                <th>{{ _lang('Message') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($guarantorRequests as $request)
                                            <tr>
                                                <td class="pl-4">{{ $request->guarantor_name }}</td>
                                                <td>{{ $request->guarantor_email }}</td>
                                                <td>
                                                    @if($request->status == 'pending')
                                                        @if($request->isExpired())
                                                            <span class="badge badge-warning">{{ _lang('Expired') }}</span>
                                                        @else
                                                            <span class="badge badge-info">{{ _lang('Pending') }}</span>
                                                        @endif
                                                    @elseif($request->status == 'accepted')
                                                        <span class="badge badge-success">{{ _lang('Accepted') }}</span>
                                                    @elseif($request->status == 'declined')
                                                        <span class="badge badge-danger">{{ _lang('Declined') }}</span>
                                                    @else
                                                        <span class="badge badge-secondary">{{ ucfirst($request->status) }}</span>
                                                    @endif
                                                </td>
                                                <td>{{ $request->created_at->format(get_date_format()) }}</td>
                                                <td>
                                                    @if($request->responded_at)
                                                        {{ $request->responded_at->format(get_date_format()) }}
                                                    @else
                                                        <span class="text-muted">{{ _lang('Not responded') }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($request->guarantor_message)
                                                        <small class="text-muted">{{ Str::limit($request->guarantor_message, 50) }}</small>
                                                    @else
                                                        <span class="text-muted">{{ _lang('No message') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Confirmed Guarantors Section -->
                        <div class="card">
                            <div class="card-header border d-flex align-items-center">
                                <span><i class="fas fa-shield-alt"></i> {{ _lang("Confirmed Guarantors") }}</span>
                                <a
                                class="btn btn-primary btn-xs ml-auto ajax-modal"
                                href="{{ route('guarantors.create') }}" data-title="{{ _lang('Add Guarantor') }}"
                                ><i class="ti-plus"></i>
                                {{ _lang("Add New") }}</a
                                >
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table id="guarantors_table" class="table table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th class="pl-4">{{ _lang('Guarantor Name') }}</th>
                                                <th>{{ _lang('Amount') }}</th>
                                                <th>{{ _lang('Status') }}</th>
                                                <th class="text-center">{{ _lang('Action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if($guarantors->count() == 0)
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">
                                                    <i class="fas fa-info-circle"></i> {{ _lang('No confirmed guarantors yet') }}
                                                </td>
                                            </tr>
                                            @endif

                                            @foreach($guarantors as $guarantor)
                                            <tr data-id="row_{{ $guarantor->id }}">
                                                <td class='pl-4 member_id'>
                                                    <i class="fas fa-user"></i> {{ $guarantor->member->first_name }} {{ $guarantor->member->last_name }}
                                                    <br><small class="text-muted">{{ $guarantor->member->email }}</small>
                                                </td>
                                                <td class='amount'>
                                                    <strong>{{ decimalPlace($guarantor->amount, currency($loan->currency->name)) }}</strong>
                                                </td>
                                                <td>
                                                    <span class="badge badge-success">{{ _lang('Confirmed') }}</span>
                                                </td>
                                                <td class="text-center">
                                                <span class="dropdown">
                                                    <button class="btn btn-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    {{ _lang('Action') }}
                                                    </button>
                                                    <form action="{{ route('guarantors.destroy', $guarantor['id']) }}" method="post">
                                                        @csrf
                                                        <input name="_method" type="hidden" value="DELETE">
                                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                            <a href="{{ route('guarantors.edit', $guarantor['id']) }}" data-title="{{ _lang('Update Guarantor') }}" class="dropdown-item dropdown-edit ajax-modal"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
                                                            <button class="btn-remove dropdown-item" type="submit"><i class="ti-trash"></i>&nbsp;{{ _lang('Delete') }}</button>
                                                        </div>
                                                    </form>
                                                </span>
                                                </td>
                                            </tr>
                                            @endforeach

                                            @if($guarantors->count() > 0)
                                            <tr class="table-info">
                                                <td class="pl-4"><strong>{{ _lang('Total Guarantee Amount') }}</strong></td>
                                                <td><strong>{{ decimalPlace($guarantors->sum('amount'), currency($loan->currency->name)) }}</strong></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade mt-4" id="collateral">
                        <div class="card">
                            <div class="card-header border d-flex align-items-center">
                                <span>{{ _lang("All Collaterals") }}</span>
                                <a
                                class="btn btn-primary btn-xs ml-auto"
                                href="{{ route('loan_collaterals.create',['loan_id' => $loan->id]) }}"
                                ><i class="ti-plus"></i>
                                {{ _lang("New Collateral") }}</a
                                >
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th class="pl-4">{{ _lang("Name") }}</th>
                                                <th>{{ _lang("Collateral Type") }}</th>
                                                <th>{{ _lang("Serial Number") }}</th>
                                                <th>{{ _lang("Estimated Price") }}</th>
                                                <th class="text-center">{{ _lang("Action") }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if($loancollaterals->count() == 0)
                                            <tr>
                                                <td colspan="5" class="text-center">{{ _lang('No Collateral Found !') }}</td>
                                            </tr>
                                            @endif

                                            @foreach($loancollaterals as $loancollateral)
                                            <tr data-id="row_{{ $loancollateral->id }}">
                                                <td class="pl-4 name">{{ $loancollateral->name }}</td>
                                                <td class="collateral_type">
                                                {{ $loancollateral->collateral_type }}
                                                </td>
                                                <td class="serial_number">
                                                {{ $loancollateral->serial_number }}
                                                </td>
                                                <td class="estimated_price">
                                                {{ decimalPlace($loancollateral->estimated_price, currency($loan->currency->name)) }}
                                                </td>
                                                <td class="text-center">
                                                    <div class="dropdown">
                                                        <button class="btn btn-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                            {{ _lang("Action") }}
                                                        </button>
                                                        <form action="{{ route('loan_collaterals.destroy', $loancollateral['id']) }}" method="post">
                                                            @csrf
                                                            <input name="_method" type="hidden" value="DELETE"/>
                                                            <div
                                                                class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                                <a href="{{ route('loan_collaterals.edit', $loancollateral['id']) }}"
                                                                class="dropdown-item dropdown-edit dropdown-edit"><i class="ti-pencil-alt"></i>{{ _lang("Edit") }}</a>
                                                                <a href="{{ route('loan_collaterals.show', $loancollateral['id']) }}"
                                                                class="dropdown-item dropdown-view dropdown-view"><i class="ti-eye"></i>{{ _lang("View") }}</a>
                                                                <button class="btn-remove dropdown-item" type="submit">
                                                                <i class="ti-trash"></i>
                                                                {{ _lang("Delete") }}
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade mt-4" id="schedule">
                        <div class="report-header">
                            <h4>{{ get_tenant_option('business_name', request()->tenant->name) }}</h4>
                            <h5>{{ _lang('Repayments Schedule') }}</h5>
                            <p>{{ $loan->borrower->name }}, {{ _lang('Loan ID').': '.$loan->loan_id }}</p>
                        </div>
                        <table class="table table-bordered report-table">
                            <thead>
                                <tr>
                                <th>{{ _lang("Date") }}</th>
                                <th class="text-right">{{ _lang("Amount to Pay") }}</th>
                                <th class="text-right">{{ _lang("Principal Amount") }}</th>
                                <th class="text-right">{{ _lang("Interest") }}</th>
                                <th class="text-right">{{ _lang("Late Penalty") }}</th>
                                <th class="text-right">{{ _lang("Balance") }}</th>
                                <th class="text-center">{{ _lang("Status") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($repayments as $repayment)
                                <tr>
                                <td>{{ $repayment->repayment_date }}</td>
                                <td class="text-right">
                                    {{ decimalPlace($repayment['amount_to_pay'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($repayment['principal_amount'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($repayment['interest'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($repayment['penalty'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($repayment['balance'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-center">
                                    @if($repayment['status'] == 0 && date('Y-m-d') > $repayment->getRawOriginal('repayment_date'))
                                    {!! xss_clean(show_status(_lang('Due'),'danger')) !!}
                                    @elseif($repayment['status'] == 0 && date('Y-m-d') <= $repayment->getRawOriginal('repayment_date'))
                                    {!! xss_clean(show_status(_lang('Unpaid'),'warning')) !!}
                                    @else
                                    {!! xss_clean(show_status(_lang('Paid'),'success')) !!}
                                    @endif
                                </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade mt-4" id="repayments">
                        <div class="report-header">
                            <h4>{{ get_tenant_option('business_name', request()->tenant->name) }}</h4>
                            <h5>{{ _lang('Loan Payments') }}</h5>
                            <p>{{ $loan->borrower->name }}, {{ _lang('Loan ID').': '.$loan->loan_id }}</p>
                        </div>
                        <table class="table table-bordered report-table" id="repayments-table">
                            <thead>
                                <tr>
                                <th>{{ _lang("Date") }}</th>
                                <th class="text-right">{{ _lang("Principal Amount") }}</th>
                                <th class="text-right">{{ _lang("Interest") }}</th>
                                <th class="text-right">{{ _lang("Late Penalty") }}</th>
                                <th class="text-right">{{ _lang("Total Amount") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($payments as $payment)
                                <tr>
                                <td>{{ $payment->paid_at }}</td>
                                <td class="text-right">
                                    {{ decimalPlace($payment['repayment_amount'] - $payment['interest'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($payment['interest'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($payment['late_penalties'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($payment['total_amount'], currency($loan->currency->name)) }}
                                </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('js-script')
<script>
   (function($) {
       "use strict";
   
   	$('.nav-tabs a').on('shown.bs.tab', function(event){
   		var tab = $(event.target).attr("href");
   		var url = "{{ route('loans.show',$loan->id) }}";
   	    history.pushState({}, null, url + "?tab=" + tab.substring(1));
   	});
   
   	@if(isset($_GET['tab']))
   	   $('.nav-tabs a[href="#{{ $_GET['tab'] }}"]').tab('show');
   	@endif
   
   })(jQuery);
</script>
@endsection