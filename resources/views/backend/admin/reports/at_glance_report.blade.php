@extends('layouts.app')

@section('title', _lang('At a Glance Report'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ _lang('At a Glance Report') }}</span>
            </div>
            <div class="card-body">
                @if(!isset($summary))
                <form class="validate" method="post" action="{{ route('reports.at_glance_report') }}">
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
                        <h4>{{ _lang('System Overview') }}</h4>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('Total Loans') }}</h5>
                                        <h3>{{ $summary['total_loans'] }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('Active Loans') }}</h5>
                                        <h3>{{ $summary['active_loans'] }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('Fully Paid Loans') }}</h5>
                                        <h3>{{ $summary['fully_paid_loans'] }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('Default Loans') }}</h5>
                                        <h3>{{ $summary['default_loans'] }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('Total Borrowers') }}</h5>
                                        <h3>{{ $summary['total_borrowers'] }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-secondary text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('Total Disbursed') }}</h5>
                                        <h3>{{ decimalPlace($summary['total_disbursed'], currency()) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-dark text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('Total Collected') }}</h5>
                                        <h3>{{ decimalPlace($summary['total_collected'], currency()) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light text-dark">
                                    <div class="card-body">
                                        <h5>{{ _lang('Total Outstanding') }}</h5>
                                        <h3>{{ decimalPlace($summary['total_outstanding'], currency()) }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5>{{ _lang('Total Fees Collected') }}</h5>
                                        <h3 class="text-success">{{ decimalPlace($summary['total_fees'], currency()) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5>{{ _lang('Recovery Rate') }}</h5>
                                        <h3 class="text-info">{{ $summary['total_loans'] > 0 ? round(($summary['fully_paid_loans'] / $summary['total_loans']) * 100, 2) : 0 }}%</h3>
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
