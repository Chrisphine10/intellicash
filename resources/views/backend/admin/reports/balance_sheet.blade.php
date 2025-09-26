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
                @if(!isset($balance_sheet_data))
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
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">&nbsp;</label>
                                <button type="submit" name="export" value="1" class="btn btn-success form-control">{{ _lang('Export to CSV') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
                @else
                <div class="row">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4>{{ _lang('Balance Sheet as of') }} {{ $as_of_date }}</h4>
                            <div>
                                <a href="{{ route('reports.balance_sheet') }}" class="btn btn-secondary btn-sm">{{ _lang('Back to Form') }}</a>
                                <form method="post" action="{{ route('reports.balance_sheet') }}" style="display: inline;">
                                    @csrf
                                    <input type="hidden" name="as_of_date" value="{{ $as_of_date }}">
                                    <input type="hidden" name="export" value="1">
                                    <button type="submit" class="btn btn-success btn-sm">{{ _lang('Export to CSV') }}</button>
                                </form>
                            </div>
                        </div>
                        
                        @if(isset($error))
                        <div class="alert alert-warning">
                            <strong>{{ _lang('Warning') }}:</strong> {{ $error }}
                        </div>
                        @endif
                        
                        @if(isset($debug))
                        <div class="alert alert-info">
                            <strong>{{ _lang('Debug Information') }}:</strong><br>
                            Date: {{ $debug['as_of_date'] }}<br>
                            Tenant ID: {{ $debug['tenant_id'] }}<br>
                            Asset Management: {{ $debug['asset_management_enabled'] ? 'Enabled' : 'Disabled' }}<br>
                            Assets: {{ $debug['assets_count'] }}<br>
                            Loans: {{ $debug['loans_count'] }}<br>
                            Bank Accounts: {{ $debug['bank_accounts_count'] }}
                        </div>
                        @endif
                        
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
                                            @if($asset_management_enabled)
                                            <tr>
                                                <td>{{ _lang('Fixed Assets') }}</td>
                                                <td class="text-right">{{ decimalPlace($assets['fixed_assets'], currency()) }}</td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Lease Receivables') }}</td>
                                                <td class="text-right">{{ decimalPlace($assets['lease_receivables'], currency()) }}</td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td>{{ _lang('Other Assets') }}</td>
                                                <td class="text-right">{{ decimalPlace($assets['other_assets'], currency()) }}</td>
                                            </tr>
                                            <tr class="table-primary">
                                                <td><strong>{{ _lang('Total Assets') }}</strong></td>
                                                <td class="text-right"><strong>{{ decimalPlace($total_assets, currency()) }}</strong></td>
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
                                            <tr>
                                                <td>{{ _lang('Accrued Expenses') }}</td>
                                                <td class="text-right">{{ decimalPlace($liabilities['accrued_expenses'], currency()) }}</td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Other Liabilities') }}</td>
                                                <td class="text-right">{{ decimalPlace($liabilities['other_liabilities'], currency()) }}</td>
                                            </tr>
                                            <tr class="table-success">
                                                <td><strong>{{ _lang('Total Liabilities') }}</strong></td>
                                                <td class="text-right"><strong>{{ decimalPlace($total_liabilities, currency()) }}</strong></td>
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
                                            <tr>
                                                <td>{{ _lang('Reserves') }}</td>
                                                <td class="text-right">{{ decimalPlace($equity['reserves'], currency()) }}</td>
                                            </tr>
                                            {{-- Asset Purchase Adjustment is now handled by proper financial transactions --}}
                                            <tr class="table-info">
                                                <td><strong>{{ _lang('Total Equity') }}</strong></td>
                                                <td class="text-right"><strong>{{ decimalPlace($total_equity, currency()) }}</strong></td>
                                            </tr>
                                            
                                            <tr class="table-warning">
                                                <td><strong>{{ _lang('Total Liabilities & Equity') }}</strong></td>
                                                <td class="text-right"><strong>{{ decimalPlace($total_liabilities + $total_equity, currency()) }}</strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Balance Sheet Summary -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h5>{{ _lang('Balance Sheet Summary') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="text-center">
                                                    <h6>{{ _lang('Total Assets') }}</h6>
                                                    <h4 class="text-primary">{{ decimalPlace($total_assets, currency()) }}</h4>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="text-center">
                                                    <h6>{{ _lang('Total Liabilities') }}</h6>
                                                    <h4 class="text-danger">{{ decimalPlace($total_liabilities, currency()) }}</h4>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="text-center">
                                                    <h6>{{ _lang('Total Equity') }}</h6>
                                                    <h4 class="text-success">{{ decimalPlace($total_equity, currency()) }}</h4>
                                                </div>
                                            </div>
                                        </div>
                                        @if($total_assets != ($total_liabilities + $total_equity))
                                        <div class="alert alert-warning mt-3">
                                            <strong>{{ _lang('Warning') }}:</strong> {{ _lang('Balance sheet does not balance. Assets') }} ({{ decimalPlace($total_assets, currency()) }}) 
                                            {{ _lang('do not equal Liabilities + Equity') }} ({{ decimalPlace($total_liabilities + $total_equity, currency()) }})
                                        </div>
                                        @else
                                        <div class="alert alert-success mt-3">
                                            <strong>{{ _lang('Success') }}:</strong> {{ _lang('Balance sheet is balanced correctly.') }}
                                        </div>
                                        @endif
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
