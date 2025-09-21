@extends('layouts.app')

@section('title', _lang('Edit VSLA Transaction'))

@section('breadcrumb')
<div class="col-lg-6 col-7">
    <h6 class="h2 text-white d-inline-block mb-0">{{ _lang('Edit VSLA Transaction') }}</h6>
    <nav aria-label="breadcrumb" class="d-none d-md-inline-block ml-md-4">
        <ol class="breadcrumb breadcrumb-links breadcrumb-dark">
            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}"><i class="fas fa-home"></i></a></li>
            <li class="breadcrumb-item"><a href="{{ route('vsla.meetings.index') }}">{{ _lang('VSLA Meetings') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('vsla.transactions.history', ['meeting_id' => $vslaTransaction->meeting_id]) }}">{{ _lang('Transaction History') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ _lang('Edit Transaction') }}</li>
        </ol>
    </nav>
</div>
<div class="col-lg-6 col-5 text-right">
    <a href="{{ route('vsla.transactions.history', ['meeting_id' => $vslaTransaction->meeting_id]) }}" class="btn btn-sm btn-neutral">
        <i class="fa fa-arrow-left"></i> {{ _lang('Back to History') }}
    </a>
</div>
@endsection

@section('content')
<div class="container-fluid mt--6">
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header border-0">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="mb-0">{{ _lang('Edit VSLA Transaction') }}</h3>
                            <p class="text-sm mb-0">{{ _lang('Meeting') }}: {{ $vslaTransaction->meeting->title }} - {{ date('M d, Y', strtotime($vslaTransaction->meeting->meeting_date)) }}</p>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if($vslaTransaction->status === 'approved')
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>{{ _lang('Warning:') }}</strong> {{ _lang('This transaction has been approved. Editing will reverse the previous transaction and create a new one.') }}
                    </div>
                    @endif
                    
                    <form action="{{ route('vsla.transactions.update', $vslaTransaction->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="member_id" class="form-control-label">{{ _lang('Member') }} <span class="text-danger">*</span></label>
                                    <select name="member_id" id="member_id" class="form-control" required>
                                        <option value="">{{ _lang('Select Member') }}</option>
                                        @foreach($members as $member)
                                        <option value="{{ $member->id }}" {{ $vslaTransaction->member_id == $member->id ? 'selected' : '' }}>
                                            {{ $member->first_name }} {{ $member->last_name }} ({{ $member->member_no }})
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('member_id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="transaction_type" class="form-control-label">{{ _lang('Transaction Type') }} <span class="text-danger">*</span></label>
                                    <select name="transaction_type" id="transaction_type" class="form-control" required>
                                        <option value="">{{ _lang('Select Transaction Type') }}</option>
                                        <option value="share_purchase" {{ $vslaTransaction->transaction_type == 'share_purchase' ? 'selected' : '' }}>{{ _lang('Share Purchase') }}</option>
                                        <option value="loan_issuance" {{ $vslaTransaction->transaction_type == 'loan_issuance' ? 'selected' : '' }}>{{ _lang('Loan Issuance') }}</option>
                                        <option value="loan_repayment" {{ $vslaTransaction->transaction_type == 'loan_repayment' ? 'selected' : '' }}>{{ _lang('Loan Repayment') }}</option>
                                        <option value="penalty_fine" {{ $vslaTransaction->transaction_type == 'penalty_fine' ? 'selected' : '' }}>{{ _lang('Penalty Fine') }}</option>
                                        <option value="welfare_contribution" {{ $vslaTransaction->transaction_type == 'welfare_contribution' ? 'selected' : '' }}>{{ _lang('Welfare Contribution') }}</option>
                                    </select>
                                    @error('transaction_type')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="amount" class="form-control-label">{{ _lang('Amount') }} <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0.01" value="{{ old('amount', $vslaTransaction->amount) }}" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">{{ currency_symbol() }}</span>
                                        </div>
                                    </div>
                                    @error('amount')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status" class="form-control-label">{{ _lang('Current Status') }}</label>
                                    <input type="text" class="form-control" value="{{ ucfirst($vslaTransaction->status) }}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description" class="form-control-label">{{ _lang('Description') }}</label>
                            <textarea name="description" id="description" class="form-control" rows="3" placeholder="{{ _lang('Enter transaction description (optional)') }}">{{ old('description', $vslaTransaction->description) }}</textarea>
                            @error('description')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-control-label">{{ _lang('Transaction Details') }}</label>
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>{{ _lang('Created By:') }}</strong> {{ $vslaTransaction->createdUser->name ?? _lang('System') }}</p>
                                                    <p><strong>{{ _lang('Created At:') }}</strong> {{ date('M d, Y H:i', strtotime($vslaTransaction->created_at)) }}</p>
                                                </div>
                                                <div class="col-md-6">
                                                    @if($vslaTransaction->updated_at != $vslaTransaction->created_at)
                                                    <p><strong>{{ _lang('Last Updated:') }}</strong> {{ date('M d, Y H:i', strtotime($vslaTransaction->updated_at)) }}</p>
                                                    @endif
                                                    @if($vslaTransaction->transaction_id)
                                                    <p><strong>{{ _lang('Linked Transaction ID:') }}</strong> {{ $vslaTransaction->transaction_id }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> {{ _lang('Update Transaction') }}
                                </button>
                                <a href="{{ route('vsla.transactions.history', ['meeting_id' => $vslaTransaction->meeting_id]) }}" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> {{ _lang('Cancel') }}
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Add form validation
    $('form').on('submit', function(e) {
        var amount = parseFloat($('#amount').val());
        if (amount <= 0) {
            e.preventDefault();
            alert('{{ _lang("Amount must be greater than 0") }}');
            return false;
        }
        
        @if($vslaTransaction->status === 'approved')
        if (!confirm('{{ _lang("Are you sure you want to update this approved transaction? This will reverse the previous transaction.") }}')) {
            e.preventDefault();
            return false;
        }
        @endif
    });
    
    // Auto-format amount input
    $('#amount').on('blur', function() {
        var value = parseFloat($(this).val());
        if (!isNaN(value)) {
            $(this).val(value.toFixed(2));
        }
    });
});
</script>
@endpush
