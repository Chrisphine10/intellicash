@extends('layouts.app')

@section('title', _lang('Profit Loss Statement'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ _lang('Profit Loss Statement') }}</span>
            </div>
            <div class="card-body">
                @if(!isset($revenue))
                <form class="validate" method="post" action="{{ route('reports.profit_loss_statement') }}">
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
                                <label class="control-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary form-control">{{ _lang('Generate Report') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
                @else
                <div class="row">
                    <div class="col-md-12">
                        <h4>{{ _lang('Profit Loss Statement') }} - {{ $date1 }} {{ _lang('to') }} {{ $date2 }}</h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-success text-white">
                                        <h5>{{ _lang('REVENUE') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <td>{{ _lang('Interest Income') }}</td>
                                                <td class="text-right">{{ decimalPlace($revenue['interest_income'], currency()) }}</td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Penalty Income') }}</td>
                                                <td class="text-right">{{ decimalPlace($revenue['penalty_income'], currency()) }}</td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Fee Income') }}</td>
                                                <td class="text-right">{{ decimalPlace($revenue['fee_income'], currency()) }}</td>
                                            </tr>
                                            <tr class="table-success">
                                                <td><strong>{{ _lang('Total Revenue') }}</strong></td>
                                                <td class="text-right"><strong>{{ decimalPlace(array_sum($revenue), currency()) }}</strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-danger text-white">
                                        <h5>{{ _lang('EXPENSES') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <td>{{ _lang('Operating Expenses') }}</td>
                                                <td class="text-right">{{ decimalPlace($expenses['operating_expenses'], currency()) }}</td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Interest Expense') }}</td>
                                                <td class="text-right">{{ decimalPlace($expenses['interest_expense'], currency()) }}</td>
                                            </tr>
                                            <tr class="table-danger">
                                                <td><strong>{{ _lang('Total Expenses') }}</strong></td>
                                                <td class="text-right"><strong>{{ decimalPlace(array_sum($expenses), currency()) }}</strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body text-center">
                                        @php
                                            $total_revenue = array_sum($revenue);
                                            $total_expenses = array_sum($expenses);
                                            $net_profit = $total_revenue - $total_expenses;
                                        @endphp
                                        
                                        <h4>{{ _lang('Net Profit/Loss') }}</h4>
                                        <h2 class="{{ $net_profit >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ decimalPlace($net_profit, currency()) }}
                                        </h2>
                                        
                                        <div class="row mt-3">
                                            <div class="col-md-4">
                                                <h6>{{ _lang('Total Revenue') }}</h6>
                                                <h5 class="text-success">{{ decimalPlace($total_revenue, currency()) }}</h5>
                                            </div>
                                            <div class="col-md-4">
                                                <h6>{{ _lang('Total Expenses') }}</h6>
                                                <h5 class="text-danger">{{ decimalPlace($total_expenses, currency()) }}</h5>
                                            </div>
                                            <div class="col-md-4">
                                                <h6>{{ _lang('Profit Margin') }}</h6>
                                                <h5 class="text-info">{{ $total_revenue > 0 ? round(($net_profit / $total_revenue) * 100, 2) : 0 }}%</h5>
                                            </div>
                                        </div>
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
