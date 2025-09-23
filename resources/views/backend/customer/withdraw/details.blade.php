@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span class="panel-title">{{ _lang('Withdrawal Request Details') }}</span>
                <div class="ml-auto">
                    @if($withdrawal->status == 0)
                        <span class="badge badge-warning">{{ _lang('Pending Approval') }}</span>
                    @elseif($withdrawal->status == 2)
                        <span class="badge badge-success">{{ _lang('Approved') }}</span>
                    @elseif($withdrawal->status == 3)
                        <span class="badge badge-danger">{{ _lang('Rejected') }}</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @php
                    $savingsAccount = \App\Models\SavingsAccount::with('savings_type.currency')->find($withdrawal->savings_account_id);
                    $paymentMethodType = $withdrawal->method ?? 'Manual';
                @endphp

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">{{ _lang('Withdrawal Information') }}</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>{{ _lang('Amount') }}:</strong></td>
                                <td>
                                    @if($savingsAccount)
                                        {{ decimalPlace($withdrawal->amount, currency($savingsAccount->savings_type->currency->name)) }}
                                    @else
                                        {{ decimalPlace($withdrawal->amount, currency('KES')) }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Account') }}:</strong></td>
                                <td>
                                    @if($savingsAccount)
                                        {{ $savingsAccount->account_number }} - {{ $savingsAccount->savings_type->name }}
                                    @else
                                        {{ _lang('Account not found') }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Payment Method') }}:</strong></td>
                                <td><span class="badge badge-info">{{ ucfirst($paymentMethodType) }}</span></td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Date') }}:</strong></td>
                                <td>{{ \Carbon\Carbon::parse($withdrawal->trans_date)->format('M d, Y H:i') }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Description') }}:</strong></td>
                                <td>{{ $withdrawal->description ?: 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">{{ _lang('Transaction Details') }}</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>{{ _lang('Transaction ID') }}:</strong></td>
                                <td>{{ $withdrawal->id }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Charge') }}:</strong></td>
                                <td>
                                    @if($savingsAccount)
                                        {{ decimalPlace($withdrawal->charge ?? 0, currency($savingsAccount->savings_type->currency->name)) }}
                                    @else
                                        {{ decimalPlace($withdrawal->charge ?? 0, currency('KES')) }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Net Amount') }}:</strong></td>
                                <td>
                                    @if($savingsAccount)
                                        {{ decimalPlace($withdrawal->amount - ($withdrawal->charge ?? 0), currency($savingsAccount->savings_type->currency->name)) }}
                                    @else
                                        {{ decimalPlace($withdrawal->amount - ($withdrawal->charge ?? 0), currency('KES')) }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Type') }}:</strong></td>
                                <td>{{ $withdrawal->type }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('DR/CR') }}:</strong></td>
                                <td>{{ strtoupper($withdrawal->dr_cr) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-12">
                        <a href="{{ route('withdraw.history') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Back to History') }}
                        </a>
                        @if($withdrawal->status == 0)
                            <a href="{{ route('withdraw.manual_methods') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> {{ _lang('New Withdrawal') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
