@extends('layouts.app')

@section('title', _lang('Loan Products Report'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ _lang('Loan Products Report') }}</span>
            </div>
            <div class="card-body">
                @if(!isset($report_data))
                <form class="validate" method="post" action="{{ route('reports.loan_products_report') }}">
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
                                        <th>{{ _lang('Product Name') }}</th>
                                        <th>{{ _lang('Interest Rate') }}</th>
                                        <th>{{ _lang('Term') }}</th>
                                        <th>{{ _lang('Min Amount') }}</th>
                                        <th>{{ _lang('Max Amount') }}</th>
                                        <th>{{ _lang('Total Loans') }}</th>
                                        <th>{{ _lang('Total Disbursed') }}</th>
                                        <th>{{ _lang('Total Collected') }}</th>
                                        <th>{{ _lang('Avg Loan Size') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report_data as $product)
                                    <tr>
                                        <td>{{ $product->name }}</td>
                                        <td>{{ $product->interest_rate }}%</td>
                                        <td>{{ $product->term }} {{ $product->term_period }}</td>
                                        <td>{{ decimalPlace($product->minimum_amount, currency()) }}</td>
                                        <td>{{ decimalPlace($product->maximum_amount, currency()) }}</td>
                                        <td>{{ $product->loans_count }}</td>
                                        <td>{{ decimalPlace($product->total_disbursed, currency()) }}</td>
                                        <td>{{ decimalPlace($product->total_collected, currency()) }}</td>
                                        <td>{{ decimalPlace($product->avg_loan_size, currency()) }}</td>
                                        <td>
                                            @if($product->status == 1)
                                                <span class="badge badge-success">{{ _lang('Active') }}</span>
                                            @else
                                                <span class="badge badge-danger">{{ _lang('Inactive') }}</span>
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
