@extends('layouts.app')

@section('title', _lang('Disbursement Report'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ _lang('Disbursement Report') }}</span>
            </div>
            <div class="card-body">
                @if(!isset($report_data))
                <form class="validate" method="post" action="{{ route('reports.disbursement_report') }}">
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
                                <label class="control-label">{{ _lang('Loan Product') }}</label>
                                <select class="form-control" name="loan_product_id">
                                    <option value="">{{ _lang('All Products') }}</option>
                                    @foreach(\App\Models\LoanProduct::all() as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
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
                                        <th>{{ _lang('Release Date') }}</th>
                                        <th>{{ _lang('Loan ID') }}</th>
                                        <th>{{ _lang('Borrower') }}</th>
                                        <th>{{ _lang('Loan Product') }}</th>
                                        <th>{{ _lang('Currency') }}</th>
                                        <th>{{ _lang('Disbursed Amount') }}</th>
                                        <th>{{ _lang('Interest Rate') }}</th>
                                        <th>{{ _lang('Term') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report_data as $loan)
                                    <tr>
                                        <td>{{ $loan->release_date }}</td>
                                        <td>{{ $loan->loan_id }}</td>
                                        <td>{{ $loan->borrower->first_name }} {{ $loan->borrower->last_name }}</td>
                                        <td>{{ $loan->loan_product->name }}</td>
                                        <td>{{ $loan->currency->name }}</td>
                                        <td>{{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}</td>
                                        <td>{{ $loan->loan_product->interest_rate }}%</td>
                                        <td>{{ $loan->loan_product->term }} {{ $loan->loan_product->term_period }}</td>
                                        <td>
                                            @if($loan->status == 1)
                                                <span class="badge badge-success">{{ _lang('Active') }}</span>
                                            @elseif($loan->status == 2)
                                                <span class="badge badge-info">{{ _lang('Fully Paid') }}</span>
                                            @elseif($loan->status == 3)
                                                <span class="badge badge-danger">{{ _lang('Default') }}</span>
                                            @else
                                                <span class="badge badge-warning">{{ _lang('Pending') }}</span>
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
