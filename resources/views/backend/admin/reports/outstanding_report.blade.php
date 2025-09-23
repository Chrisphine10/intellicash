@extends('layouts.app')

@section('title', _lang('Outstanding Report'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ _lang('Outstanding Report') }}</span>
            </div>
            <div class="card-body">
                @if(!isset($report_data))
                <form class="validate" method="post" action="{{ route('reports.outstanding_report') }}">
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
                                        <th>{{ _lang('Currency') }}</th>
                                        <th>{{ _lang('Applied Amount') }}</th>
                                        <th>{{ _lang('Total Paid') }}</th>
                                        <th>{{ _lang('Outstanding Amount') }}</th>
                                        <th>{{ _lang('Release Date') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report_data as $loan)
                                    <tr>
                                        <td>{{ $loan->loan_id }}</td>
                                        <td>{{ $loan->borrower->first_name }} {{ $loan->borrower->last_name }}</td>
                                        <td>{{ $loan->loan_product->name }}</td>
                                        <td>{{ $loan->currency->name }}</td>
                                        <td>{{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}</td>
                                        <td>{{ decimalPlace($loan->total_paid, currency($loan->currency->name)) }}</td>
                                        <td>{{ decimalPlace($loan->outstanding_amount, currency($loan->currency->name)) }}</td>
                                        <td>{{ $loan->release_date }}</td>
                                        <td>
                                            <span class="badge badge-success">{{ _lang('Active') }}</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="6">{{ _lang('Total Outstanding') }}</th>
                                        <th>{{ decimalPlace($report_data->sum('outstanding_amount'), currency()) }}</th>
                                        <th colspan="2"></th>
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
