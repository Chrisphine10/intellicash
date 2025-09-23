@extends('layouts.app')

@section('title', _lang('Fees Report'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ _lang('Fees Report') }}</span>
            </div>
            <div class="card-body">
                @if(!isset($report_data))
                <form class="validate" method="post" action="{{ route('reports.fees_report') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Date From') }}</label>
                                <input type="text" class="form-control datepicker" name="date1" value="{{ old('date1') }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Date To') }}</label>
                                <input type="text" class="form-control datepicker" name="date2" value="{{ old('date2') }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary form-control">{{ _lang('Generate Report') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
                @else
                <div class="row">
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Payment Date') }}</th>
                                        <th>{{ _lang('Loan ID') }}</th>
                                        <th>{{ _lang('Borrower') }}</th>
                                        <th>{{ _lang('Interest Collected') }}</th>
                                        <th>{{ _lang('Penalty Collected') }}</th>
                                        <th>{{ _lang('Total Fees') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report_data as $payment)
                                    <tr>
                                        <td>{{ $payment->paid_at }}</td>
                                        <td>{{ $payment->loan->loan_id }}</td>
                                        <td>{{ $payment->loan->borrower->first_name }} {{ $payment->loan->borrower->last_name }}</td>
                                        <td>{{ decimalPlace($payment->interest, currency()) }}</td>
                                        <td>{{ decimalPlace($payment->late_penalties, currency()) }}</td>
                                        <td>{{ decimalPlace($payment->total_fees, currency()) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3">{{ _lang('Total') }}</th>
                                        <th>{{ decimalPlace($report_data->sum('interest'), currency()) }}</th>
                                        <th>{{ decimalPlace($report_data->sum('late_penalties'), currency()) }}</th>
                                        <th>{{ decimalPlace($report_data->sum('total_fees'), currency()) }}</th>
                                    </tr>
                                </tfoot>
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
