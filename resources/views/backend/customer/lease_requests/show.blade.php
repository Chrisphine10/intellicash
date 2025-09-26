@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <span class="panel-title">{{ _lang('Lease Request Details') }}</span>
                    <a class="btn btn-secondary btn-sm float-right" href="{{ route('lease-requests.member.index') }}">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back') }}
                    </a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-6">
                            <h5>{{ _lang('Request Information') }}</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Request Number') }}:</strong></td>
                                    <td>{{ $leaseRequest->request_number }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Status') }}:</strong></td>
                                    <td>
                                        @if($leaseRequest->status == 'pending')
                                            <span class="badge badge-warning">{{ _lang('Pending') }}</span>
                                        @elseif($leaseRequest->status == 'approved')
                                            <span class="badge badge-success">{{ _lang('Approved') }}</span>
                                        @elseif($leaseRequest->status == 'rejected')
                                            <span class="badge badge-danger">{{ _lang('Rejected') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Requested Date') }}:</strong></td>
                                    <td>{{ $leaseRequest->created_at }}</td>
                                </tr>
                                @if($leaseRequest->processed_at)
                                <tr>
                                    <td><strong>{{ _lang('Processed Date') }}:</strong></td>
                                    <td>{{ $leaseRequest->processed_at }}</td>
                                </tr>
                                @endif
                                @if($leaseRequest->processedBy)
                                <tr>
                                    <td><strong>{{ _lang('Processed By') }}:</strong></td>
                                    <td>{{ $leaseRequest->processedBy->name }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h5>{{ _lang('Asset Information') }}</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Asset Name') }}:</strong></td>
                                    <td>{{ $leaseRequest->asset->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Category') }}:</strong></td>
                                    <td>{{ $leaseRequest->asset->category->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Asset Code') }}:</strong></td>
                                    <td>{{ $leaseRequest->asset->asset_code }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Description') }}:</strong></td>
                                    <td>{{ $leaseRequest->asset->description ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h5>{{ _lang('Lease Details') }}</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Start Date') }}:</strong></td>
                                    <td>{{ $leaseRequest->start_date }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('End Date') }}:</strong></td>
                                    <td>{{ $leaseRequest->end_date ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Duration') }}:</strong></td>
                                    <td>{{ $leaseRequest->requested_days }} {{ _lang('days') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Daily Rate') }}:</strong></td>
                                    <td>{{ formatAmount($leaseRequest->daily_rate) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Total Amount') }}:</strong></td>
                                    <td>{{ formatAmount($leaseRequest->total_amount) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Deposit Amount') }}:</strong></td>
                                    <td>{{ formatAmount($leaseRequest->deposit_amount) }}</td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h5>{{ _lang('Payment Information') }}</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Payment Account') }}:</strong></td>
                                    <td>{{ $leaseRequest->paymentAccount->account_number }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Account Type') }}:</strong></td>
                                    <td>{{ $leaseRequest->paymentAccount->savings_type->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Total Payment Required') }}:</strong></td>
                                    <td><strong>{{ formatAmount($leaseRequest->total_amount + $leaseRequest->deposit_amount) }}</strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <h5>{{ _lang('Reason for Lease') }}</h5>
                            <div class="card">
                                <div class="card-body">
                                    {{ $leaseRequest->reason }}
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($leaseRequest->admin_notes)
                    <div class="row">
                        <div class="col-md-12">
                            <h5>{{ _lang('Admin Notes') }}</h5>
                            <div class="card">
                                <div class="card-body">
                                    {{ $leaseRequest->admin_notes }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($leaseRequest->rejection_reason)
                    <div class="row">
                        <div class="col-md-12">
                            <h5>{{ _lang('Rejection Reason') }}</h5>
                            <div class="card">
                                <div class="card-body">
                                    <div class="alert alert-danger">
                                        {{ $leaseRequest->rejection_reason }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($leaseRequest->status == 'approved')
                    <div class="row">
                        <div class="col-md-12">
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> 
                                {{ _lang('Your lease request has been approved! Payment has been processed from your account.') }}
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
