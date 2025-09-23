@extends('layouts.app')

@section('title', _lang('Loan Officer Report'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="card-title">{{ _lang('Loan Officer Report') }}</span>
            </div>
            <div class="card-body">
                @if(!isset($report_data))
                <form class="validate" method="post" action="{{ route('reports.loan_officer_report') }}">
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
                                <label class="control-label">{{ _lang('Loan Officer') }}</label>
                                <select class="form-control" name="officer_id">
                                    <option value="">{{ _lang('All Officers') }}</option>
                                    @foreach(\App\Models\User::where('user_type', 'user')->get() as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
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
                                        <th>{{ _lang('Created Date') }}</th>
                                        <th>{{ _lang('Loan ID') }}</th>
                                        <th>{{ _lang('Borrower') }}</th>
                                        <th>{{ _lang('Loan Product') }}</th>
                                        <th>{{ _lang('Applied Amount') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                        <th>{{ _lang('Loan Officer') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report_data as $loan)
                                    <tr>
                                        <td>{{ $loan->created_at }}</td>
                                        <td>{{ $loan->loan_id }}</td>
                                        <td>{{ $loan->borrower->first_name }} {{ $loan->borrower->last_name }}</td>
                                        <td>{{ $loan->loan_product->name }}</td>
                                        <td>{{ decimalPlace($loan->applied_amount, currency()) }}</td>
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
                                        <td>{{ $loan->created_by->name ?? 'N/A' }}</td>
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
