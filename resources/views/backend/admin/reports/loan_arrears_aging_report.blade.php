@extends('layouts.app')

@section('title', _lang('Loan Arrears Aging Report'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ _lang('Loan Arrears Aging Report') }}</span>
            </div>
            <div class="card-body">
                @if(!isset($report_data))
                <form class="validate" method="post" action="{{ route('reports.loan_arrears_aging_report') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">{{ _lang('Generate Report') }}</button>
                                <a href="{{ route('reports.export.loan_arrears_aging_report') }}" class="btn btn-success ml-2">{{ _lang('Export CSV') }}</a>
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
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report_data as $repayment)
                                    <tr>
                                        <td>{{ $repayment->loan->loan_id }}</td>
                                        <td>{{ $repayment->loan->borrower->first_name }} {{ $repayment->loan->borrower->last_name }}</td>
                                        <td>{{ $repayment->loan->loan_product->name }}</td>
                                        <td>{{ $repayment->repayment_date }}</td>
                                        <td>
                                            <span class="badge badge-{{ $repayment->days_overdue > 90 ? 'danger' : ($repayment->days_overdue > 30 ? 'warning' : 'info') }}">
                                                {{ $repayment->days_overdue }} {{ _lang('days') }}
                                            </span>
                                        </td>
                                        <td>{{ decimalPlace($repayment->principal_amount, currency()) }}</td>
                                        <td>{{ decimalPlace($repayment->interest, currency()) }}</td>
                                        <td>{{ decimalPlace($repayment->penalty, currency()) }}</td>
                                        <td>{{ decimalPlace($repayment->amount_to_pay, currency()) }}</td>
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
