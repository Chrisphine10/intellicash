@extends('layouts.app')

@section('title', _lang('Portfolio At Risk Report'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ _lang('Portfolio At Risk (PAR) Report') }}</span>
            </div>
            <div class="card-body">
                @if(!isset($report_data))
                <form class="validate" method="post" action="{{ route('reports.portfolio_at_risk_report') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">{{ _lang('Generate Report') }}</button>
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
                                        <th>{{ _lang('Loan ID') }}</th>
                                        <th>{{ _lang('Borrower') }}</th>
                                        <th>{{ _lang('Loan Product') }}</th>
                                        <th>{{ _lang('Repayment Date') }}</th>
                                        <th>{{ _lang('Days Overdue') }}</th>
                                        <th>{{ _lang('Principal Due') }}</th>
                                        <th>{{ _lang('Interest Due') }}</th>
                                        <th>{{ _lang('Penalty Due') }}</th>
                                        <th>{{ _lang('Total Due') }}</th>
                                        <th>{{ _lang('Risk Level') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report_data as $repayment)
                                    <tr>
                                        <td>{{ $repayment->loan->loan_id }}</td>
                                        <td>{{ $repayment->loan->borrower->first_name }} {{ $repayment->loan->borrower->last_name }}</td>
                                        <td>{{ $repayment->loan->loan_product->name }}</td>
                                        <td>{{ $repayment->repayment_date }}</td>
                                        <td>{{ $repayment->days_overdue }}</td>
                                        <td>{{ decimalPlace($repayment->principal_amount, currency()) }}</td>
                                        <td>{{ decimalPlace($repayment->interest, currency()) }}</td>
                                        <td>{{ decimalPlace($repayment->penalty, currency()) }}</td>
                                        <td>{{ decimalPlace($repayment->amount_to_pay, currency()) }}</td>
                                        <td>
                                            @if($repayment->days_overdue > 90)
                                                <span class="badge badge-danger">{{ _lang('High Risk') }}</span>
                                            @elseif($repayment->days_overdue > 30)
                                                <span class="badge badge-warning">{{ _lang('Medium Risk') }}</span>
                                            @else
                                                <span class="badge badge-info">{{ _lang('Low Risk') }}</span>
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
            </div>
        </div>
    </div>
</div>
@endsection
