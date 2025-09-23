@extends('layouts.app')

@section('title', _lang('Balance Sheet'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ _lang('Balance Sheet') }}</span>
            </div>
            <div class="card-body">
                @if(!isset($assets))
                <form class="validate" method="post" action="{{ route('reports.balance_sheet') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('As of Date') }}</label>
                                <input type="text" class="form-control datepicker" name="as_of_date" value="{{ old('as_of_date', date('Y-m-d')) }}" required>
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
                        <h4>{{ _lang('Balance Sheet as of') }} {{ $as_of_date }}</h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h5>{{ _lang('ASSETS') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <td>{{ _lang('Cash in Hand') }}</td>
                                                <td class="text-right">{{ decimalPlace($assets['cash_in_hand'], currency()) }}</td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Bank Balances') }}</td>
                                                <td class="text-right">{{ decimalPlace($assets['bank_balances'], currency()) }}</td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Loan Portfolio') }}</td>
                                                <td class="text-right">{{ decimalPlace($assets['loan_portfolio'], currency()) }}</td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Fixed Assets') }}</td>
                                                <td class="text-right">{{ decimalPlace($assets['fixed_assets'], currency()) }}</td>
                                            </tr>
                                            <tr class="table-primary">
                                                <td><strong>{{ _lang('Total Assets') }}</strong></td>
                                                <td class="text-right"><strong>{{ decimalPlace(array_sum($assets), currency()) }}</strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-success text-white">
                                        <h5>{{ _lang('LIABILITIES & EQUITY') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>{{ _lang('LIABILITIES') }}</strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Savings Deposits') }}</td>
                                                <td class="text-right">{{ decimalPlace($liabilities['savings_deposits'], currency()) }}</td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Borrowings') }}</td>
                                                <td class="text-right">{{ decimalPlace($liabilities['borrowings'], currency()) }}</td>
                                            </tr>
                                            <tr class="table-success">
                                                <td><strong>{{ _lang('Total Liabilities') }}</strong></td>
                                                <td class="text-right"><strong>{{ decimalPlace(array_sum($liabilities), currency()) }}</strong></td>
                                            </tr>
                                            
                                            <tr>
                                                <td><strong>{{ _lang('EQUITY') }}</strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Retained Earnings') }}</td>
                                                <td class="text-right">{{ decimalPlace($equity['retained_earnings'], currency()) }}</td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Capital') }}</td>
                                                <td class="text-right">{{ decimalPlace($equity['capital'], currency()) }}</td>
                                            </tr>
                                            <tr class="table-info">
                                                <td><strong>{{ _lang('Total Equity') }}</strong></td>
                                                <td class="text-right"><strong>{{ decimalPlace(array_sum($equity), currency()) }}</strong></td>
                                            </tr>
                                            
                                            <tr class="table-warning">
                                                <td><strong>{{ _lang('Total Liabilities & Equity') }}</strong></td>
                                                <td class="text-right"><strong>{{ decimalPlace(array_sum($liabilities) + array_sum($equity), currency()) }}</strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
