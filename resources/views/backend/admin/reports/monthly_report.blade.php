@extends('layouts.app')

@section('title', _lang('Monthly Report'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ _lang('Monthly Report') }}</span>
            </div>
            <div class="card-body">
                @if(!isset($monthly_data))
                <form class="validate" method="post" action="{{ route('reports.monthly_report') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Year') }}</label>
                                <select class="form-control" name="year" required>
                                    @for($i = date('Y'); $i >= date('Y') - 5; $i--)
                                    <option value="{{ $i }}" {{ $i == date('Y') ? 'selected' : '' }}>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Month') }}</label>
                                <select class="form-control" name="month" required>
                                    @for($i = 1; $i <= 12; $i++)
                                    <option value="{{ $i }}" {{ $i == date('m') ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $i, 1)) }}</option>
                                    @endfor
                                </select>
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
                        <h4>{{ _lang('Monthly Summary for') }} {{ date('F', mktime(0, 0, 0, $month, 1)) }} {{ $year }}</h4>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('Loans Disbursed') }}</h5>
                                        <h3>{{ decimalPlace($monthly_data['loans_disbursed'], currency()) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('Loans Collected') }}</h5>
                                        <h3>{{ decimalPlace($monthly_data['loans_collected'], currency()) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('New Borrowers') }}</h5>
                                        <h3>{{ $monthly_data['new_borrowers'] }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('Fees Collected') }}</h5>
                                        <h3>{{ decimalPlace($monthly_data['fees_collected'], currency()) }}</h3>
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
