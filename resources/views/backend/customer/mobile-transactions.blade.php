@extends('layouts.mobile-app')

@section('content')
<div class="container-fluid">
    <!-- Header with Filter -->
    <div class="mobile-card">
        <div class="card-header">
            <h5 class="card-title">{{ _lang('Transactions') }}</h5>
            <p class="card-subtitle">{{ _lang('Your transaction history') }}</p>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-6">
                    <select class="form-control" id="filter-type">
                        <option value="">{{ _lang('All Types') }}</option>
                        <option value="deposit">{{ _lang('Deposits') }}</option>
                        <option value="withdraw">{{ _lang('Withdrawals') }}</option>
                        <option value="transfer">{{ _lang('Transfers') }}</option>
                        <option value="loan">{{ _lang('Loans') }}</option>
                    </select>
                </div>
                <div class="col-6">
                    <select class="form-control" id="filter-status">
                        <option value="">{{ _lang('All Status') }}</option>
                        <option value="completed">{{ _lang('Completed') }}</option>
                        <option value="pending">{{ _lang('Pending') }}</option>
                        <option value="failed">{{ _lang('Failed') }}</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction List -->
    <div class="mobile-card">
        <div class="card-body">
            @if(count($transactions ?? []) > 0)
                <ul class="mobile-list" id="transaction-list">
                    @foreach($transactions as $transaction)
                    @php
                        $symbol = $transaction->dr_cr == 'dr' ? '-' : '+';
                        $class  = $transaction->dr_cr == 'dr' ? 'text-danger' : 'text-success';
                        $statusClass = '';
                        $statusText = '';
                        
                        switch($transaction->status) {
                            case 'completed':
                                $statusClass = 'success';
                                $statusText = _lang('Completed');
                                break;
                            case 'pending':
                                $statusClass = 'warning';
                                $statusText = _lang('Pending');
                                break;
                            case 'failed':
                                $statusClass = 'danger';
                                $statusText = _lang('Failed');
                                break;
                            default:
                                $statusClass = 'info';
                                $statusText = ucfirst($transaction->status);
                        }
                    @endphp
                    <li class="mobile-list-item transaction-item" 
                        data-type="{{ $transaction->type }}" 
                        data-status="{{ $transaction->status }}">
                        <div class="item-icon" style="background: {{ $transaction->dr_cr == 'dr' ? '#f8d7da' : '#d4edda' }}; color: {{ $transaction->dr_cr == 'dr' ? '#721c24' : '#155724' }};">
                            <i class="fas fa-{{ $transaction->dr_cr == 'dr' ? 'arrow-down' : 'arrow-up' }}"></i>
                        </div>
                        <div class="item-content">
                            <h6 class="item-title">{{ ucwords(str_replace('_',' ',$transaction->type)) }}</h6>
                            <p class="item-subtitle">{{ $transaction->trans_date }} • {{ $transaction->account->account_number }}</p>
                            <p class="item-subtitle" style="font-weight: 600; color: {{ $transaction->dr_cr == 'dr' ? '#dc3545' : '#28a745' }};">
                                {{ $symbol }}{{ decimalPlace($transaction->amount, currency($transaction->account->savings_type->currency->name)) }}
                            </p>
                            <span class="badge badge-{{ $statusClass }} badge-sm">{{ $statusText }}</span>
                        </div>
                        <i class="fas fa-chevron-right item-arrow"></i>
                    </li>
                    @endforeach
                </ul>
                
                <!-- Load More Button -->
                <div class="text-center mt-3">
                    <button class="mobile-btn mobile-btn-secondary" id="load-more-btn">
                        <i class="fas fa-plus"></i>
                        {{ _lang('Load More') }}
                    </button>
                </div>
            @else
                <div class="text-center py-4">
                    <i class="fas fa-receipt" style="font-size: 48px; color: var(--text-muted); margin-bottom: 15px;"></i>
                    <h6 style="color: var(--text-muted);">{{ _lang('No transactions found') }}</h6>
                    <p style="color: var(--text-muted); font-size: 14px;">{{ _lang('Your transactions will appear here') }}</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mobile-card">
        <div class="card-header">
            <h5 class="card-title">{{ _lang('Quick Actions') }}</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-6 mb-3">
                    <a href="{{ route('deposit.automatic_methods') }}" class="mobile-btn">
                        <i class="fas fa-plus"></i>
                        {{ _lang('Deposit') }}
                    </a>
                </div>
                <div class="col-6 mb-3">
                    <a href="{{ route('withdraw.manual_methods') }}" class="mobile-btn mobile-btn-secondary">
                        <i class="fas fa-minus"></i>
                        {{ _lang('Withdraw') }}
                    </a>
                </div>
                <div class="col-6 mb-3">
                    <a href="{{ route('transfer.own_account_transfer') }}" class="mobile-btn mobile-btn-secondary">
                        <i class="fas fa-exchange-alt"></i>
                        {{ _lang('Transfer') }}
                    </a>
                </div>
                <div class="col-6 mb-3">
                    <a href="{{ route('loans.loan_products') }}" class="mobile-btn mobile-btn-secondary">
                        <i class="fas fa-hand-holding-usd"></i>
                        {{ _lang('Apply Loan') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.badge-sm {
    font-size: 10px;
    padding: 4px 8px;
    border-radius: 4px;
}

.transaction-item {
    transition: all 0.3s ease;
}

.transaction-item:hover {
    background: var(--bg-light);
    transform: translateX(5px);
}

.filtered-out {
    display: none !important;
}

#filter-type, #filter-status {
    font-size: 14px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--bg-white);
}

#filter-type:focus, #filter-status:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Loading animation */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid var(--primary-color);
    border-top: 2px solid transparent;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
$(document).ready(function() {
    // Filter functionality
    $('#filter-type, #filter-status').on('change', function() {
        filterTransactions();
    });
    
    function filterTransactions() {
        const typeFilter = $('#filter-type').val();
        const statusFilter = $('#filter-status').val();
        
        $('.transaction-item').each(function() {
            const $item = $(this);
            const itemType = $item.data('type');
            const itemStatus = $item.data('status');
            
            let showItem = true;
            
            if (typeFilter && itemType !== typeFilter) {
                showItem = false;
            }
            
            if (statusFilter && itemStatus !== statusFilter) {
                showItem = false;
            }
            
            if (showItem) {
                $item.removeClass('filtered-out').show();
            } else {
                $item.addClass('filtered-out').hide();
            }
        });
        
        // Check if any items are visible
        const visibleItems = $('.transaction-item:not(.filtered-out)').length;
        if (visibleItems === 0) {
            $('#transaction-list').append(`
                <div class="text-center py-4" id="no-results">
                    <i class="fas fa-search" style="font-size: 48px; color: var(--text-muted); margin-bottom: 15px;"></i>
                    <h6 style="color: var(--text-muted);">{{ _lang('No transactions match your filters') }}</h6>
                    <button class="mobile-btn mobile-btn-secondary mt-3" onclick="clearFilters()">
                        {{ _lang('Clear Filters') }}
                    </button>
                </div>
            `);
        } else {
            $('#no-results').remove();
        }
    }
    
    // Load more functionality
    $('#load-more-btn').on('click', function() {
        const $btn = $(this);
        $btn.addClass('loading');
        
        // Simulate loading (replace with actual AJAX call)
        setTimeout(() => {
            $btn.removeClass('loading');
            // Add more transactions here
            $btn.text('{{ _lang("All transactions loaded") }}').prop('disabled', true);
        }, 2000);
    });
    
    // Pull to refresh
    let startY = 0;
    let currentY = 0;
    let isRefreshing = false;
    
    document.addEventListener('touchstart', function(e) {
        startY = e.touches[0].clientY;
    }, { passive: true });
    
    document.addEventListener('touchmove', function(e) {
        currentY = e.touches[0].clientY;
        if (currentY - startY > 100 && window.scrollY === 0 && !isRefreshing) {
            isRefreshing = true;
            $.toast({
                heading: '{{ _lang("Refreshing") }}',
                text: '{{ _lang("Updating transactions...") }}',
                position: 'top-right',
                loaderBg: '#007bff',
                icon: 'info',
                hideAfter: 2000,
                stack: 6
            });
            setTimeout(() => {
                location.reload();
            }, 1000);
        }
    }, { passive: true });
    
    // Transaction item click handler
    $('.transaction-item').on('click', function() {
        // Add transaction detail modal or navigation here
        $.toast({
            heading: '{{ _lang("Transaction Details") }}',
            text: '{{ _lang("Transaction detail view would open here") }}',
            position: 'top-right',
            loaderBg: '#007bff',
            icon: 'info',
            hideAfter: 3000,
            stack: 6
        });
    });
});

function clearFilters() {
    $('#filter-type, #filter-status').val('').trigger('change');
    $('#no-results').remove();
}
</script>
@endsection
