@extends('layouts.app')

@section('title', _lang('Borrowers Report'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ _lang('Borrowers Report') }}</span>
            </div>
            <div class="card-body">
                @if(!isset($report_data))
                <form class="validate" method="post" action="{{ route('reports.borrowers_report') }}">
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
                                <label class="control-label">{{ _lang('Loan Status') }}</label>
                                <select class="form-control" name="status">
                                    <option value="">{{ _lang('All Status') }}</option>
                                    <option value="0">{{ _lang('Pending') }}</option>
                                    <option value="1">{{ _lang('Active') }}</option>
                                    <option value="2">{{ _lang('Fully Paid') }}</option>
                                    <option value="3">{{ _lang('Default') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Gender') }}</label>
                                <select class="form-control" name="gender">
                                    <option value="">{{ _lang('All Gender') }}</option>
                                    <option value="Male">{{ _lang('Male') }}</option>
                                    <option value="Female">{{ _lang('Female') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">{{ _lang('Generate Report') }}</button>
                                <a href="{{ route('reports.export.borrowers_report') }}" class="btn btn-success ml-2">{{ _lang('Export CSV') }}</a>
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
                                        <th>{{ _lang('Member No') }}</th>
                                        <th>{{ _lang('Name') }}</th>
                                        <th>{{ _lang('Gender') }}</th>
                                        <th>{{ _lang('Email') }}</th>
                                        <th>{{ _lang('Mobile') }}</th>
                                        <th>{{ _lang('Total Loans') }}</th>
                                        <th>{{ _lang('Active Loans') }}</th>
                                        <th>{{ _lang('Total Borrowed') }}</th>
                                        <th>{{ _lang('Total Paid') }}</th>
                                        <th>{{ _lang('Outstanding') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report_data as $borrower)
                                    <tr>
                                        <td>{{ $borrower->member_no }}</td>
                                        <td>{{ $borrower->first_name }} {{ $borrower->last_name }}</td>
                                        <td>{{ $borrower->gender }}</td>
                                        <td>{{ $borrower->email }}</td>
                                        <td>{{ $borrower->mobile }}</td>
                                        <td>{{ $borrower->loans->count() }}</td>
                                        <td>{{ $borrower->loans->where('status', 1)->count() }}</td>
                                        <td>{{ decimalPlace($borrower->loans->sum('applied_amount'), currency()) }}</td>
                                        <td>{{ decimalPlace($borrower->loans->sum('total_paid'), currency()) }}</td>
                                        <td>{{ decimalPlace($borrower->loans->sum('applied_amount') - $borrower->loans->sum('total_paid'), currency()) }}</td>
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
