@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('Create New VSLA Transaction') }}</h4>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('vsla.transactions.store') }}" class="validate">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Meeting') }}</label>
                                <select class="form-control" name="meeting_id" required>
                                    <option value="">{{ _lang('Select Meeting') }}</option>
                                    @foreach($meetings as $meeting)
                                    <option value="{{ $meeting->id }}" {{ $selectedMeeting == $meeting->id ? 'selected' : '' }}>
                                        {{ $meeting->meeting_number }} - {{ $meeting->meeting_date }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Member') }}</label>
                                <select class="form-control" name="member_id" required>
                                    <option value="">{{ _lang('Select Member') }}</option>
                                    @foreach($members as $member)
                                    <option value="{{ $member->id }}">
                                        {{ $member->first_name }} {{ $member->last_name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Transaction Type') }}</label>
                                <select class="form-control" name="transaction_type" id="transaction_type" required>
                                    <option value="">{{ _lang('Select Type') }}</option>
                                    <option value="share_purchase">{{ _lang('Share Purchase') }}</option>
                                    <option value="loan_issuance">{{ _lang('Loan Issuance') }}</option>
                                    <option value="loan_repayment">{{ _lang('Loan Repayment') }}</option>
                                    <option value="penalty_fine">{{ _lang('Penalty Fine') }}</option>
                                    <option value="welfare_contribution">{{ _lang('Welfare Contribution') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Amount') }}</label>
                                <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label">{{ _lang('Description') }}</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="{{ _lang('Enter transaction description...') }}"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">{{ _lang('Create Transaction') }}</button>
                        <a href="{{ route('vsla.transactions.index') }}" class="btn btn-secondary">{{ _lang('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
