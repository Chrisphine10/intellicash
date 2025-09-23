@extends('layouts.app')

@section('head')
<style>
.bg-danger-light {
    background-color: #f8d7da !important;
}
.border-danger {
    border-color: #dc3545 !important;
}
</style>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('Bulk Transaction Processing') }} - {{ $meeting->meeting_number }}</h4>
                <small class="text-muted">{{ $meeting->meeting_date }} at {{ $meeting->meeting_time }}</small>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>{{ _lang('Instructions:') }}</strong> 
                    <ul class="mb-0 mt-2">
                        <li>{{ _lang('Use "Add Transaction" to add individual transactions') }}</li>
                        <li>{{ _lang('Use "Add All Members" to create share purchase rows for all members') }}</li>
                        <li>{{ _lang('Use "Add Default Amounts" to populate amounts based on VSLA settings') }}</li>
                        <li>{{ _lang('Amounts will auto-fill when you select transaction types') }}</li>
                        <li>{{ _lang('Each member can only have one transaction of each type per meeting') }}</li>
                        <li>{{ _lang('Use "Validate Transactions" to check for duplicates before submitting') }}</li>
                    </ul>
                </div>

                <form method="post" action="{{ route('vsla.transactions.bulk_store') }}" class="validate" id="bulkTransactionForm">
                    @csrf
                    <input type="hidden" name="meeting_id" value="{{ $meeting->id }}">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="btn-toolbar" role="toolbar">
                                <div class="btn-group mr-2" role="group">
                                    <button type="button" class="btn btn-success" id="addTransactionRow">
                                        <i class="fas fa-plus"></i> {{ _lang('Add Transaction') }}
                                    </button>
                                    <button type="button" class="btn btn-warning" id="addAllMembers" title="{{ _lang('Add all members with share purchase transactions') }}">
                                        <i class="fas fa-users"></i> {{ _lang('Add All Members') }}
                                    </button>
                                </div>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-info" id="addDefaultAmounts" title="{{ _lang('Populate amounts based on VSLA settings') }}">
                                        <i class="fas fa-magic"></i> {{ _lang('Add Default Amounts') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="transactionsContainer">
                        <!-- Transaction rows will be added here dynamically -->
                    </div>

                    <div class="form-group mt-4">
                        <button type="button" class="btn btn-warning" id="validateTransactions">
                            <i class="fas fa-check-circle"></i> {{ _lang('Validate Transactions') }}
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBulkTransactions">
                            <i class="fas fa-save"></i> {{ _lang('Process All Transactions') }}
                        </button>
                        <a href="{{ route('vsla.transactions.history', ['meeting_id' => $meeting->id]) }}" class="btn btn-info">
                            <i class="fas fa-history"></i> {{ _lang('View Transaction History') }}
                        </a>
                        <a href="{{ route('vsla.transactions.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Back to Transactions') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Row Template -->
<template id="transactionRowTemplate">
    <div class="transaction-row border rounded p-3 mb-3" data-index="">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label class="control-label">{{ _lang('Member') }} <span class="text-danger">*</span></label>
                    <select class="form-control member-select" name="transactions[INDEX][member_id]" required>
                        <option value="">{{ _lang('Select Member') }}</option>
                        @foreach($members as $member)
                        <option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }} ({{ $member->member_no }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label class="control-label">{{ _lang('Type') }} <span class="text-danger">*</span></label>
                    <select class="form-control transaction-type" name="transactions[INDEX][transaction_type]" required>
                        <option value="">{{ _lang('Select Type') }}</option>
                        <option value="share_purchase">{{ _lang('Share Purchase') }}</option>
                        <option value="loan_issuance">{{ _lang('Loan Issuance') }}</option>
                        <option value="loan_repayment">{{ _lang('Loan Repayment') }}</option>
                        <option value="penalty_fine">{{ _lang('Penalty Fine') }}</option>
                        <option value="welfare_contribution">{{ _lang('Welfare Contribution') }}</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label class="control-label amount-label">{{ _lang('Amount') }} <span class="text-danger">*</span></label>
                    <label class="control-label shares-label" style="display: none;">{{ _lang('Shares') }} <span class="text-danger">*</span></label>
                    <input type="number" class="form-control amount-input" name="transactions[INDEX][amount]" step="0.01" min="0.01" required>
                    <input type="number" class="form-control shares-input" name="transactions[INDEX][shares]" min="1" style="display: none;">
                    <small class="form-text text-muted shares-cost" style="display: none;"></small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label class="control-label">{{ _lang('Description') }}</label>
                    <input type="text" class="form-control" name="transactions[INDEX][description]" placeholder="{{ _lang('Optional description...') }}">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label class="control-label">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm btn-block remove-transaction" style="display: none;">
                        <i class="fas fa-trash"></i> {{ _lang('Remove') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    let transactionIndex = 0;
    const vslaSettings = @json($vslaSettings ?? null);
    const baseCurrency = '{{ get_base_currency() }}';
    
    // Add first transaction row
    addTransactionRow();
    
    // Add transaction row
    $('#addTransactionRow').click(function() {
        addTransactionRow();
    });
    
    // Add all members
    $('#addAllMembers').click(function() {
        // Clear existing rows
        $('#transactionsContainer').empty();
        transactionIndex = 0;
        
        // Add rows for each member with default share_purchase type
        @foreach($members as $member)
        addTransactionRow({{ $member->id }}, 'share_purchase');
        @endforeach
    });
    
    // Add default amounts
    $('#addDefaultAmounts').click(function() {
        $('.transaction-row').each(function() {
            const type = $(this).find('.transaction-type').val();
            const amountInput = $(this).find('.amount-input');
            
            if (type) {
                let defaultAmount = 0;
                switch(type) {
                    case 'share_purchase':
                        defaultAmount = vslaSettings ? (vslaSettings.share_amount || 0) : 0;
                        break;
                    case 'penalty_fine':
                        defaultAmount = vslaSettings ? (vslaSettings.penalty_amount || 0) : 0;
                        break;
                    case 'welfare_contribution':
                        defaultAmount = vslaSettings ? (vslaSettings.welfare_amount || 0) : 0;
                        break;
                    case 'loan_issuance':
                        defaultAmount = vslaSettings ? (vslaSettings.max_loan_amount || 1000) : 1000;
                        break;
                    case 'loan_repayment':
                        defaultAmount = vslaSettings ? (vslaSettings.max_loan_amount || 1000) : 1000;
                        break;
                }
                amountInput.val(defaultAmount);
            }
        });
    });
    
    // Validate transactions
    $('#validateTransactions').click(function() {
        if (validateTransactions()) {
            alert('{{ _lang("All transactions are valid! No duplicates found.") }}');
        }
    });
    
    // Remove transaction row
    $(document).on('click', '.remove-transaction', function() {
        $(this).closest('.transaction-row').remove();
        updateRemoveButtons();
    });
    
    // Auto-fill default amounts when type changes and toggle between amount/shares
    $(document).on('change', '.transaction-type', function() {
        const type = $(this).val();
        const $row = $(this).closest('.transaction-row');
        const amountInput = $row.find('.amount-input');
        const sharesInput = $row.find('.shares-input');
        const amountLabel = $row.find('.amount-label');
        const sharesLabel = $row.find('.shares-label');
        const sharesCost = $row.find('.shares-cost');
        
        // Check for duplicate transaction types for the same member
        if (type) {
            const memberId = $row.find('.member-select').val();
            if (memberId && isDuplicateTransaction(memberId, type, $row)) {
                alert('{{ _lang("This member already has a transaction of this type. Please select a different type or member.") }}');
                $(this).val('');
                return;
            }
        }
        
        // Toggle between shares and amount input based on transaction type
        if (type === 'share_purchase') {
            // Show shares input, hide amount input
            amountInput.hide().prop('required', false);
            amountLabel.hide();
            sharesInput.show().prop('required', true);
            sharesLabel.show();
            
            // Show share cost information
            const shareAmount = vslaSettings ? (vslaSettings.share_amount || 0) : 0;
            sharesCost.text(`Cost per share: ${shareAmount} ${baseCurrency}`).show();
            
            // Set default shares if not already set
            if (!sharesInput.val()) {
                const defaultShares = vslaSettings ? (vslaSettings.min_shares_per_member || 1) : 1;
                sharesInput.val(defaultShares);
                // Set min and max based on VSLA settings
                const maxShares = vslaSettings ? (vslaSettings.max_shares_per_meeting || 3) : 3;
                sharesInput.attr('min', vslaSettings ? (vslaSettings.min_shares_per_member || 1) : 1);
                sharesInput.attr('max', maxShares);
            }
        } else {
            // Show amount input, hide shares input
            sharesInput.hide().prop('required', false);
            sharesLabel.hide();
            sharesCost.hide();
            amountInput.show().prop('required', true);
            amountLabel.show();
            
            // Set default amount if not already set
            if (type && !amountInput.val()) {
                let defaultAmount = 0;
                switch(type) {
                    case 'penalty_fine':
                        defaultAmount = vslaSettings ? (vslaSettings.penalty_amount || 0) : 0;
                        break;
                    case 'welfare_contribution':
                        defaultAmount = vslaSettings ? (vslaSettings.welfare_amount || 0) : 0;
                        break;
                    case 'loan_issuance':
                        defaultAmount = vslaSettings ? (vslaSettings.max_loan_amount || 1000) : 1000;
                        break;
                    case 'loan_repayment':
                        defaultAmount = vslaSettings ? (vslaSettings.max_loan_amount || 1000) : 1000;
                        break;
                }
                amountInput.val(defaultAmount);
            }
        }
    });
    
    // Check for duplicate transaction types when member changes
    $(document).on('change', '.member-select', function() {
        const memberId = $(this).val();
        const type = $(this).closest('.transaction-row').find('.transaction-type').val();
        
        if (memberId && type && isDuplicateTransaction(memberId, type, $(this).closest('.transaction-row'))) {
            alert('{{ _lang("This member already has a transaction of this type. Please select a different type or member.") }}');
            $(this).val('');
        }
    });
    
    // Function to check for duplicate transaction types for the same member
    function isDuplicateTransaction(memberId, transactionType, currentRow) {
        let isDuplicate = false;
        $('.transaction-row').not(currentRow).each(function() {
            const rowMemberId = $(this).find('.member-select').val();
            const rowTransactionType = $(this).find('.transaction-type').val();
            
            if (rowMemberId === memberId && rowTransactionType === transactionType) {
                isDuplicate = true;
                return false; // Break out of loop
            }
        });
        return isDuplicate;
    }
    
    // Function to validate all transactions for duplicates
    function validateTransactions() {
        const transactions = [];
        let hasDuplicates = false;
        const duplicateErrors = [];
        
        // Clear previous highlights
        $('.transaction-row').removeClass('border-danger bg-danger-light');
        
        $('.transaction-row').each(function(index) {
            const memberId = $(this).find('.member-select').val();
            const type = $(this).find('.transaction-type').val();
            
            if (memberId && type) {
                const key = memberId + '_' + type;
                if (transactions.includes(key)) {
                    hasDuplicates = true;
                    const memberName = $(this).find('.member-select option:selected').text();
                    duplicateErrors.push(`Row ${index + 1}: ${memberName} - ${type.replace('_', ' ').toUpperCase()}`);
                    
                    // Highlight duplicate rows
                    $(this).addClass('border-danger bg-danger-light');
                } else {
                    transactions.push(key);
                }
            }
        });
        
        if (hasDuplicates) {
            alert('{{ _lang("Duplicate transactions found:") }}\n\n' + duplicateErrors.join('\n') + '\n\n{{ _lang("Please remove duplicates before submitting.") }}');
            return false;
        }
        
        return true;
    }
    
    function addTransactionRow(selectedMemberId = null, defaultTransactionType = null) {
        const template = $('#transactionRowTemplate').html();
        const newRow = template.replace(/INDEX/g, transactionIndex);
        const $newRow = $(newRow);
        
        if (selectedMemberId) {
            $newRow.find('.member-select').val(selectedMemberId);
        }
        
        if (defaultTransactionType) {
            // Check if this would create a duplicate
            if (selectedMemberId && isDuplicateTransaction(selectedMemberId, defaultTransactionType, $newRow)) {
                alert('{{ _lang("Cannot add duplicate transaction type for this member. Please select a different member or type.") }}');
                return;
            }
            
            $newRow.find('.transaction-type').val(defaultTransactionType);
            
            // Auto-fill amount or shares based on type
            if (defaultTransactionType === 'share_purchase') {
                // For share purchases, show shares input
                const sharesInput = $newRow.find('.shares-input');
                const amountInput = $newRow.find('.amount-input');
                const amountLabel = $newRow.find('.amount-label');
                const sharesLabel = $newRow.find('.shares-label');
                const sharesCost = $newRow.find('.shares-cost');
                
                amountInput.hide().prop('required', false);
                amountLabel.hide();
                sharesInput.show().prop('required', true);
                sharesLabel.show();
                
                const shareAmount = vslaSettings ? (vslaSettings.share_amount || 0) : 0;
                sharesCost.text(`Cost per share: ${shareAmount} ${baseCurrency}`).show();
                
                const defaultShares = vslaSettings ? (vslaSettings.min_shares_per_member || 1) : 1;
                const maxShares = vslaSettings ? (vslaSettings.max_shares_per_meeting || 3) : 3;
                sharesInput.val(defaultShares);
                sharesInput.attr('min', vslaSettings ? (vslaSettings.min_shares_per_member || 1) : 1);
                sharesInput.attr('max', maxShares);
            } else {
                // For other transaction types, show amount input
                const amountInput = $newRow.find('.amount-input');
                let defaultAmount = 0;
                switch(defaultTransactionType) {
                    case 'penalty_fine':
                        defaultAmount = vslaSettings ? (vslaSettings.penalty_amount || 0) : 0;
                        break;
                    case 'welfare_contribution':
                        defaultAmount = vslaSettings ? (vslaSettings.welfare_amount || 0) : 0;
                        break;
                    case 'loan_issuance':
                        defaultAmount = vslaSettings ? (vslaSettings.max_loan_amount || 1000) : 1000;
                        break;
                    case 'loan_repayment':
                        defaultAmount = vslaSettings ? (vslaSettings.max_loan_amount || 1000) : 1000;
                        break;
                }
                amountInput.val(defaultAmount);
            }
        }
        
        $('#transactionsContainer').append($newRow);
        transactionIndex++;
        updateRemoveButtons();
    }
    
    function updateRemoveButtons() {
        const rows = $('.transaction-row');
        rows.find('.remove-transaction').hide();
        
        if (rows.length > 1) {
            rows.find('.remove-transaction').show();
        }
    }
    
    // Form submission
    $('#bulkTransactionForm').submit(function(e) {
        e.preventDefault();
        
        // Validate for duplicates before processing
        if (!validateTransactions()) {
            return;
        }
        
        const formData = new FormData(this);
        const transactions = [];
        
        // Collect transaction data
        $('.transaction-row').each(function(index) {
            const memberId = $(this).find('.member-select').val();
            const type = $(this).find('.transaction-type').val();
            const description = $(this).find('input[name*="[description]"]').val();
            
            let amount = 0;
            let shares = 0;
            
            if (type === 'share_purchase') {
                shares = $(this).find('.shares-input').val();
                // Calculate amount from shares
                amount = shares * (vslaSettings ? (vslaSettings.share_amount || 0) : 0);
            } else {
                amount = $(this).find('.amount-input').val();
            }
            
            if (memberId && type && (amount > 0 || shares > 0)) {
                transactions.push({
                    member_id: memberId,
                    transaction_type: type,
                    amount: amount,
                    shares: shares,
                    description: description || ''
                });
            }
        });
        
        if (transactions.length === 0) {
            alert('{{ _lang("Please add at least one transaction") }}');
            return;
        }
        
        // Add transactions to form data
        formData.delete('transactions');
        transactions.forEach((transaction, index) => {
            Object.keys(transaction).forEach(key => {
                formData.append(`transactions[${index}][${key}]`, transaction[key]);
            });
        });
        
        // Submit form
        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.result === 'success') {
                    alert(response.message);
                    window.location.href = '{{ route("vsla.transactions.index") }}';
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                if (response && response.message) {
                    alert(response.message);
                } else {
                    alert('{{ _lang("An error occurred while processing transactions") }}');
                }
            }
        });
    });
});
</script>
@endsection
