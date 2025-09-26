@extends('layouts.app')

@section('title', _lang('Collections Report'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ _lang('Collections Report') }}</span>
                <div class="card-tools">
                    <a href="{{ url()->previous() }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if(!isset($report_data))
                <form class="validate" method="post" action="{{ route('reports.collections_report') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Date From') }}</label>
                                <input type="text" class="form-control datepicker" name="date1" value="{{ old('date1') }}" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Date To') }}</label>
                                <input type="text" class="form-control datepicker" name="date2" value="{{ old('date2') }}" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Collector') }}</label>
                                <select class="form-control" name="collector_id">
                                    <option value="">{{ _lang('All Collectors') }}</option>
                                    @foreach(\App\Models\User::where('user_type', 'user')->get() as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary form-control">
                                    <i class="fas fa-chart-bar"></i> {{ _lang('Generate Report') }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <a href="{{ url()->previous() }}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> {{ _lang('Back') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
                @else
                <div class="row mb-3">
                    <div class="col-md-12">
                        <a href="{{ route('reports.collections_report') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Back to Filter') }}
                        </a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Payment Date') }}</th>
                                        <th>{{ _lang('Loan ID') }}</th>
                                        <th>{{ _lang('Borrower') }}</th>
                                        <th>{{ _lang('Principal Paid') }}</th>
                                        <th>{{ _lang('Interest Paid') }}</th>
                                        <th>{{ _lang('Penalty Paid') }}</th>
                                        <th>{{ _lang('Total Paid') }}</th>
                                        <th>{{ _lang('Collector') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report_data as $payment)
                                    <tr>
                                        <td>{{ $payment->paid_at }}</td>
                                        <td>{{ $payment->loan->loan_id }}</td>
                                        <td>{{ $payment->loan->borrower->first_name }} {{ $payment->loan->borrower->last_name }}</td>
                                        <td>{{ decimalPlace($payment->repayment_amount, currency()) }}</td>
                                        <td>{{ decimalPlace($payment->interest, currency()) }}</td>
                                        <td>{{ decimalPlace($payment->late_penalties, currency()) }}</td>
                                        <td>{{ decimalPlace($payment->total_amount, currency()) }}</td>
                                        <td>{{ $payment->loan->created_by->name ?? 'N/A' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
