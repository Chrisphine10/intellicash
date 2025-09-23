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
<div class="col-lg-12">
    <div class="card">
        <div class="card-header">
            <span class="panel-title">{{ _lang('Loan Details') }}</span>
        </div>

        <div class="card-body">
            <!-- Nav tabs -->
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#loan_details">{{ _lang('Loan Details') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#guarantor">{{ _lang("Guarantor") }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#collateral">{{ _lang('Collateral') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#schedule">{{ _lang('Repayments Schedule') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#repayments">{{ _lang('Repayments') }}</a>
                </li>
            </ul>

            <!-- Tab panes -->
            <div class="tab-content">
                <div class="tab-pane active" id="loan_details">
                    <table class="table table-bordered mt-4">
                        <tr><td>{{ _lang('Loan ID') }}</td><td>{{ $loan->loan_id }}</td></tr>
                        <tr><td>{{ _lang('Borrower') }}</td><td>{{ $loan->borrower->name }}</td></tr>
                        <tr><td>{{ _lang('Currency') }}</td><td>{{ $loan->currency->name }}</td></tr>
                        <tr>
                            <td>{{ _lang('Status') }}</td>
                            <td>
                                {!! $loan->status == 0 ? xss_clean(show_status(_lang('Pending'), 'warning')) : xss_clean(show_status(_lang('Approved'), 'success')) !!}
                            </td>
                        </tr>
                        <tr>
                            <td>{{ _lang('First Payment Date') }}</td>
                            <td>{{ $loan->first_payment_date }}</td>
                        </tr>
                        <tr>
                            <td>{{ _lang('Release Date') }}</td>
                            <td>{{ $loan->release_date }}</td>
                        </tr>
                        <tr>
                            <td>{{ _lang('Applied Amount') }}</td>
                            <td>{{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}</td>
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
                        <tr><td>{{ _lang('Late Payment Penalties') }}</td><td>{{ $loan->late_payment_penalties }} %</td></tr>
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
                        <tr>
                            <td>{{ _lang('Attachment') }}</td>
                            <td>
                                {!! $loan->attachment == "" ? '' : '<a href="'. asset('public/uploads/media/'.$loan->attachment) .'" target="_blank">'._lang('Download').'</a>' !!}
                            </td>
                        </tr>

                        @if($loan->status == 1)
                            <tr>
                                <td>{{ _lang('Approved Date') }}</td>
                                <td>{{ $loan->approved_date }}</td>
                            </tr>
                            <tr>
                                <td>{{ _lang('Approved By') }}</td>
                                <td>{{ $loan->approved_by->name }}</td>
                            </tr>
                        @endif

                        <tr><td>{{ _lang('Description') }}</td><td>{{ $loan->description }}</td></tr>
                        <tr><td>{{ _lang('Remarks') }}</td><td>{{ $loan->remarks }}</td></tr>
                    </table>
                </div>

                <div class="tab-pane fade mt-4" id="guarantor">
                    <!-- Guarantor Requests Section -->
                    @if(isset($guarantorRequests) && $guarantorRequests->count() > 0)
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-user-friends"></i> {{ _lang('Guarantor Requests') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>{{ _lang('Guarantor Name') }}</th>
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
                                            <td>{{ $request->guarantor_name }}</td>
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
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-shield-alt"></i> {{ _lang('Confirmed Guarantors') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="guarantors_table" class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>{{ _lang('Guarantor Name') }}</th>
                                            <th>{{ _lang('Amount') }}</th>
                                            <th>{{ _lang('Status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(isset($guarantors) && $guarantors->count() == 0)
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">
                                                <i class="fas fa-info-circle"></i> {{ _lang('No confirmed guarantors yet') }}
                                            </td>
                                        </tr>
                                        @endif

                                        @if(isset($guarantors))
                                        @foreach($guarantors as $guarantor)
                                        <tr data-id="row_{{ $guarantor->id }}">
                                            <td class='member_id'>
                                                <i class="fas fa-user"></i> {{ $guarantor->member->first_name }} {{ $guarantor->member->last_name }}
                                                <br><small class="text-muted">{{ $guarantor->member->email }}</small>
                                            </td>
                                            <td class='amount'>
                                                <strong>{{ decimalPlace($guarantor->amount, currency($loan->currency->name)) }}</strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-success">{{ _lang('Confirmed') }}</span>
                                            </td>
                                        </tr>
                                        @endforeach

                                        @if($guarantors->count() > 0)
                                        <tr class="table-info">
                                            <td><strong>{{ _lang('Total Guarantee Amount') }}</strong></td>
                                            <td><strong>{{ decimalPlace($guarantors->sum('amount'), currency($loan->currency->name)) }}</strong></td>
                                            <td></td>
                                        </tr>
                                        @endif
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade mt-4" id="collateral">
                    <div class="table-responsive">
                        <table class="table table-bordered data-table">
                            <thead>
                                <tr>
                                    <th>{{ _lang('Name') }}</th>
                                    <th>{{ _lang('Collateral Type') }}</th>
                                    <th>{{ _lang('Serial Number') }}</th>
                                    <th>{{ _lang('Estimated Price') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loan->collaterals as $loancollateral)
                                <tr data-id="row_{{ $loancollateral->id }}">
                                    <td class='name'>{{ $loancollateral->name }}</td>
                                    <td class='collateral_type'>{{ $loancollateral->collateral_type }}</td>
                                    <td class='serial_number'>{{ $loancollateral->serial_number }}</td>
                                    <td class='estimated_price'>{{ $loancollateral->estimated_price }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
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
                                <th class="text-right">{{ _lang("Late Penalty") }}</th>
                                <th class="text-right">{{ _lang("Principal Amount") }}</th>
                                <th class="text-right">{{ _lang("Interest") }}</th>
                                <th class="text-right">{{ _lang("Balance") }}</th>
                                <th class="text-center">{{ _lang("Status") }}</th>
                                <th class="text-center">{{ _lang("Action") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loan->repayments as $repayment)
                            <tr>
                                <td>{{ $repayment->repayment_date }}</td>
                                <td class="text-right">
                                    {{ decimalPlace($repayment['amount_to_pay'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($repayment['penalty'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($repayment['principal_amount'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($repayment['interest'], currency($loan->currency->name)) }}
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
                                <td class="text-center">
                                    @if($repayment['status'] == 0 && $loan->next_payment->id == $repayment->id)
                                        <a href="{{ route('loans.loan_payment', $repayment->loan_id) }}" class="btn btn-primary btn-xs">{{ _lang('Pay Now') }}</a>
                                    @elseif($repayment['status'] == 0 && date('Y-m-d') > $repayment->repayment_date)
                                        <span class="btn btn-secondary btn-xs disabled">{{ _lang('No Action') }}</span>
                                    @else
                                        <span class="btn btn-success btn-xs px-4 disabled">{{ _lang('Paid') }}</span>
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
                    <table class="table table-bordered report-table">
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
                            @foreach($loan->payments as $payment)
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
@endsection
