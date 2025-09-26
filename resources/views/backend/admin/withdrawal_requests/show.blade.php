@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span class="panel-title">{{ _lang('Withdrawal Request Details') }}</span>
                <div class="ml-auto">
                    @if($withdrawRequest->status == 0)
                        <span class="badge badge-warning">{{ _lang('Pending Approval') }}</span>
                    @elseif($withdrawRequest->status == 2)
                        <span class="badge badge-success">{{ _lang('Approved') }}</span>
                    @elseif($withdrawRequest->status == 3)
                        <span class="badge badge-danger">{{ _lang('Rejected') }}</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">{{ _lang('Member Information') }}</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>{{ _lang('Name') }}:</strong></td>
                                <td>{{ $withdrawRequest->member->first_name }} {{ $withdrawRequest->member->last_name }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Member Number') }}:</strong></td>
                                <td>{{ $withdrawRequest->member->member_no }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Email') }}:</strong></td>
                                <td>{{ $withdrawRequest->member->email }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Mobile') }}:</strong></td>
                                <td>{{ $withdrawRequest->member->mobile }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">{{ _lang('Withdrawal Details') }}</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>{{ _lang('Amount') }}:</strong></td>
                                <td>{{ decimalPlace($withdrawRequest->amount, currency($withdrawRequest->account->savings_type->currency->name)) }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Account') }}:</strong></td>
                                <td>{{ $withdrawRequest->account->account_number }} - {{ $withdrawRequest->account->savings_type->name }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Date') }}:</strong></td>
                                <td>{{ $withdrawRequest->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Description') }}:</strong></td>
                                <td>{{ $withdrawRequest->description ?: 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                @if($paymentMethod)
                <div class="row mt-4">
                    <div class="col-md-6">
                        <h6 class="text-primary">{{ _lang('Payment Method') }}</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>{{ _lang('Type') }}:</strong></td>
                                <td><span class="badge badge-info">{{ ucfirst($paymentMethod->payment_method_type) }}</span></td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Bank Account') }}:</strong></td>
                                <td>{{ $paymentMethod->bank_name }} - {{ $paymentMethod->account_name }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Account Number') }}:</strong></td>
                                <td>{{ $paymentMethod->account_number }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Currency') }}:</strong></td>
                                <td>{{ $paymentMethod->currency->name }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">{{ _lang('Recipient Details') }}</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>{{ _lang('Name') }}:</strong></td>
                                <td>{{ e($requirements['recipient_details']['name'] ?? 'N/A') }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Mobile') }}:</strong></td>
                                <td>{{ e($requirements['recipient_details']['mobile'] ?? 'N/A') }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Account Number') }}:</strong></td>
                                <td>{{ e($requirements['recipient_details']['account_number'] ?? 'N/A') }}</td>
                            </tr>
                            @if(isset($requirements['recipient_details']['bank_code']))
                            <tr>
                                <td><strong>{{ _lang('Bank Code') }}:</strong></td>
                                <td>{{ e($requirements['recipient_details']['bank_code']) }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                </div>
                @endif

                @if($withdrawRequest->status == 3 && isset($requirements['rejection_reason']))
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="alert alert-danger">
                            <h6 class="text-danger">{{ _lang('Rejection Reason') }}</h6>
                            <p>{{ e($requirements['rejection_reason']) }}</p>
                        </div>
                    </div>
                </div>
                @endif

                @if($withdrawRequest->status == 0)
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="text-center">
                            <form method="POST" action="{{ route('admin.withdrawal_requests.approve', $withdrawRequest->id) }}" style="display: inline;">
                                @csrf
                                <button type="submit" class="btn btn-success btn-lg" 
                                        onclick="return confirm('{{ _lang('Are you sure you want to approve this withdrawal request?') }}')">
                                    <i class="fas fa-check"></i> {{ _lang('Approve Withdrawal') }}
                                </button>
                            </form>
                            
                            <button type="button" class="btn btn-danger btn-lg ml-2" data-toggle="modal" data-target="#rejectModal">
                                <i class="fas fa-times"></i> {{ _lang('Reject Withdrawal') }}
                            </button>
                        </div>
                    </div>
                </div>
                @endif

                <div class="row mt-4">
                    <div class="col-md-12">
                        <a href="{{ route('admin.withdrawal_requests.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Back to List') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ _lang('Reject Withdrawal Request') }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.withdrawal_requests.reject', $withdrawRequest->id) }}">
                @csrf
                <div class="modal-body">
                    <p>{{ _lang('Please provide a reason for rejecting this withdrawal request:') }}</p>
                    <div class="form-group">
                        <label for="rejection_reason">{{ _lang('Rejection Reason') }} <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="rejection_reason" id="rejection_reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ _lang('Cancel') }}</button>
                    <button type="submit" class="btn btn-danger">{{ _lang('Reject') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
