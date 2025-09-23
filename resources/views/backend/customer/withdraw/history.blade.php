@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-sm-flex align-items-center justify-content-between">
                <span class="panel-title">{{ _lang('Withdrawal History') }}</span>
                <div>
                    <a class="btn btn-primary btn-xs" href="{{ route('withdraw.manual_methods') }}">
                        <i class="fas fa-plus mr-1"></i>{{ _lang('New Withdrawal') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if($withdrawals->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered data-table">
                            <thead>
                                <tr>
                                    <th>{{ _lang('Date') }}</th>
                                    <th>{{ _lang('Account') }}</th>
                                    <th>{{ _lang('Amount') }}</th>
                                    <th>{{ _lang('Charge') }}</th>
                                    <th>{{ _lang('Net Amount') }}</th>
                                    <th>{{ _lang('Method') }}</th>
                                    <th>{{ _lang('Status') }}</th>
                                    <th>{{ _lang('Description') }}</th>
                                    <th>{{ _lang('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($withdrawals as $withdrawal)
                                @php
                                    $savingsAccount = \App\Models\SavingsAccount::with('savings_type.currency')->find($withdrawal->savings_account_id);
                                    $paymentMethodType = $withdrawal->method ?? 'Manual';
                                @endphp
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($withdrawal->trans_date)->format('M d, Y H:i') }}</td>
                                    <td>
                                        @if($savingsAccount)
                                            <strong>{{ $savingsAccount->account_number }}</strong><br>
                                            <small class="text-muted">{{ $savingsAccount->savings_type->name }}</small>
                                        @else
                                            <span class="text-muted">{{ _lang('Account not found') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if($savingsAccount)
                                            {{ decimalPlace($withdrawal->amount, currency($savingsAccount->savings_type->currency->name)) }}
                                        @else
                                            {{ decimalPlace($withdrawal->amount, currency('KES')) }}
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if($savingsAccount)
                                            {{ decimalPlace($withdrawal->charge ?? 0, currency($savingsAccount->savings_type->currency->name)) }}
                                        @else
                                            {{ decimalPlace($withdrawal->charge ?? 0, currency('KES')) }}
                                        @endif
                                    </td>
                                    <td class="text-right font-weight-bold">
                                        @if($savingsAccount)
                                            {{ decimalPlace($withdrawal->amount - ($withdrawal->charge ?? 0), currency($savingsAccount->savings_type->currency->name)) }}
                                        @else
                                            {{ decimalPlace($withdrawal->amount - ($withdrawal->charge ?? 0), currency('KES')) }}
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-info">{{ ucfirst($paymentMethodType) }}</span>
                                    </td>
                                    <td>
                                        @if($withdrawal->status == 0)
                                            <span class="badge badge-warning">{{ _lang('Pending Approval') }}</span>
                                        @elseif($withdrawal->status == 1)
                                            <span class="badge badge-success">{{ _lang('Approved') }}</span>
                                        @elseif($withdrawal->status == 2)
                                            <span class="badge badge-danger">{{ _lang('Rejected') }}</span>
                                        @else
                                            <span class="badge badge-secondary">{{ _lang('Unknown') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $withdrawal->description ?: '-' }}</td>
                                    <td>
                                        <a href="{{ route('withdraw.request_details', $withdrawal->id) }}" class="btn btn-info btn-xs">
                                            <i class="fas fa-eye"></i> {{ _lang('View') }}
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-center">
                        {{ $withdrawals->links() }}
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-money-check fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">{{ _lang('No withdrawals found') }}</h5>
                        <p class="text-muted">{{ _lang('You haven\'t made any withdrawals yet') }}</p>
                        <a href="{{ route('withdraw.manual_methods') }}" class="btn btn-primary">
                            <i class="fas fa-plus mr-1"></i>{{ _lang('Make Your First Withdrawal') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
