@extends('layouts.mobile-app')

@section('content')
<div class="container-fluid">
    <!-- Welcome Section -->
    <div class="mobile-card">
        <div class="card-header">
            <div class="d-flex align-items-center">
                <div class="user-avatar" style="width: 50px; height: 50px; border-radius: 50%; background: var(--primary-light); display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                    <i class="fas fa-user" style="font-size: 24px; color: var(--primary-color);"></i>
                </div>
                <div>
                    <h5 class="card-title">{{ _lang('Welcome back,') }} {{ Auth::user()->name }}!</h5>
                    <p class="card-subtitle">{{ _lang('Here\'s your account overview') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
        @php
            $totalBalance = 0;
            $totalLoans = 0;
            $upcomingPayments = 0;
            $recentTransactions = count($recent_transactions ?? []);
        @endphp
        
        @foreach(get_account_details(auth()->user()->member->id) as $account)
            @php $totalBalance += $account->balance - $account->blocked_amount; @endphp
        @endforeach
        
        @foreach($loans ?? [] as $loan)
            @php $totalLoans += $loan->principal_amount; @endphp
        @endforeach
        
        @foreach($loans ?? [] as $loan)
            @php $upcomingPayments += $loan->next_payment->amount_to_pay ?? 0; @endphp
        @endforeach

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <h3 class="stat-value">{{ decimalPlace($totalBalance, currency()) }}</h3>
            <p class="stat-label">{{ _lang('Total Balance') }}</p>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-hand-holding-usd"></i>
            </div>
            <h3 class="stat-value">{{ count($loans ?? []) }}</h3>
            <p class="stat-label">{{ _lang('Active Loans') }}</p>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <h3 class="stat-value">{{ decimalPlace($upcomingPayments, currency()) }}</h3>
            <p class="stat-label">{{ _lang('Due Payments') }}</p>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <h3 class="stat-value">{{ $recentTransactions }}</h3>
            <p class="stat-label">{{ _lang('Recent Transactions') }}</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mobile-card">
        <div class="card-header">
            <h5 class="card-title">{{ _lang('Quick Actions') }}</h5>
            <p class="card-subtitle">{{ _lang('Common tasks') }}</p>
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

    <!-- Account Overview -->
    <div class="mobile-card">
        <div class="card-header">
            <h5 class="card-title">{{ _lang('Account Overview') }}</h5>
            <p class="card-subtitle">{{ _lang('Your accounts at a glance') }}</p>
        </div>
        <div class="card-body">
            <ul class="mobile-list">
                @foreach(get_account_details(auth()->user()->member->id) as $account)
                <li class="mobile-list-item">
                    <div class="item-icon">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="item-content">
                        <h6 class="item-title">{{ $account->savings_type->name }}</h6>
                        <p class="item-subtitle">{{ $account->account_number }} • {{ $account->savings_type->currency->name }}</p>
                        <p class="item-subtitle" style="font-weight: 600; color: var(--primary-color);">
                            {{ decimalPlace($account->balance - $account->blocked_amount, currency($account->savings_type->currency->name)) }}
                        </p>
                    </div>
                    <i class="fas fa-chevron-right item-arrow"></i>
                </li>
                @endforeach
            </ul>
        </div>
    </div>

    <!-- Upcoming Loan Payments -->
    @if(count($loans ?? []) > 0)
    <div class="mobile-card">
        <div class="card-header">
            <h5 class="card-title">{{ _lang('Upcoming Payments') }}</h5>
            <p class="card-subtitle">{{ _lang('Loan payments due soon') }}</p>
        </div>
        <div class="card-body">
            <ul class="mobile-list">
                @foreach($loans as $loan)
                <li class="mobile-list-item">
                    <div class="item-icon" style="background: {{ $loan->next_payment->getRawOriginal('repayment_date') >= date('Y-m-d') ? '#d4edda' : '#f8d7da' }}; color: {{ $loan->next_payment->getRawOriginal('repayment_date') >= date('Y-m-d') ? '#155724' : '#721c24' }};">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="item-content">
                        <h6 class="item-title">{{ $loan->loan_id }}</h6>
                        <p class="item-subtitle">{{ $loan->next_payment->repayment_date }}</p>
                        <p class="item-subtitle" style="font-weight: 600; color: var(--primary-color);">
                            {{ decimalPlace($loan->next_payment->amount_to_pay, currency($loan->currency->name)) }}
                        </p>
                    </div>
                    <a href="{{ route('loans.loan_payment',$loan->id) }}" class="mobile-btn" style="padding: 8px 16px; font-size: 14px; width: auto; margin: 0;">
                        {{ _lang('Pay') }}
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <!-- Recent Transactions -->
    <div class="mobile-card">
        <div class="card-header">
            <h5 class="card-title">{{ _lang('Recent Transactions') }}</h5>
            <p class="card-subtitle">{{ _lang('Your latest activity') }}</p>
        </div>
        <div class="card-body">
            @if(count($recent_transactions ?? []) > 0)
                <ul class="mobile-list">
                    @foreach($recent_transactions as $transaction)
                    @php
                        $symbol = $transaction->dr_cr == 'dr' ? '-' : '+';
                        $class  = $transaction->dr_cr == 'dr' ? 'text-danger' : 'text-success';
                    @endphp
                    <li class="mobile-list-item">
                        <div class="item-icon" style="background: {{ $transaction->dr_cr == 'dr' ? '#f8d7da' : '#d4edda' }}; color: {{ $transaction->dr_cr == 'dr' ? '#721c24' : '#155724' }};">
                            <i class="fas fa-{{ $transaction->dr_cr == 'dr' ? 'arrow-down' : 'arrow-up' }}"></i>
                        </div>
                        <div class="item-content">
                            <h6 class="item-title">{{ ucwords(str_replace('_',' ',$transaction->type)) }}</h6>
                            <p class="item-subtitle">{{ $transaction->trans_date }} • {{ $transaction->account->account_number }}</p>
                            <p class="item-subtitle" style="font-weight: 600; color: {{ $transaction->dr_cr == 'dr' ? '#dc3545' : '#28a745' }};">
                                {{ $symbol }}{{ decimalPlace($transaction->amount, currency($transaction->account->savings_type->currency->name)) }}
                            </p>
                        </div>
                        <a href="{{ route('trasnactions.details', $transaction->id) }}" class="mobile-btn mobile-btn-secondary" style="padding: 8px 16px; font-size: 14px; width: auto; margin: 0;">
                            {{ _lang('View') }}
                        </a>
                    </li>
                    @endforeach
                </ul>
                
                <div class="text-center mt-3">
                    <a href="{{ route('transactions.index') }}" class="mobile-btn mobile-btn-secondary">
                        {{ _lang('View All Transactions') }}
                    </a>
                </div>
            @else
                <div class="text-center py-4">
                    <i class="fas fa-receipt" style="font-size: 48px; color: var(--text-muted); margin-bottom: 15px;"></i>
                    <h6 style="color: var(--text-muted);">{{ _lang('No transactions yet') }}</h6>
                    <p style="color: var(--text-muted); font-size: 14px;">{{ _lang('Your transaction history will appear here') }}</p>
                </div>
            @endif
        </div>
    </div>

    <!-- PWA Install Prompt -->
    @if(get_option('pwa_enabled', 1) && !isset($_COOKIE['pwa_dismissed']))
    <div class="mobile-card" id="pwa-install-prompt" style="background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%); color: white;">
        <div class="card-body text-center">
            <i class="fas fa-mobile-alt" style="font-size: 48px; margin-bottom: 15px;"></i>
            <h5 style="color: white; margin-bottom: 10px;">{{ _lang('Install App') }}</h5>
            <p style="color: rgba(255,255,255,0.9); font-size: 14px; margin-bottom: 20px;">{{ _lang('Get quick access to your account with our mobile app') }}</p>
            <div class="row">
                <div class="col-6">
                    <button id="install-pwa-btn" class="mobile-btn" style="background: white; color: var(--primary-color); border: none;">
                        <i class="fas fa-download"></i>
                        {{ _lang('Install') }}
                    </button>
                </div>
                <div class="col-6">
                    <button id="dismiss-pwa-btn" class="mobile-btn mobile-btn-secondary" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
                        {{ _lang('Later') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<style>
/* Additional mobile-specific styles */
.user-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.stat-card:hover {
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

.mobile-list-item:hover {
    background: var(--bg-light);
}

/* Pulse animation for new notifications */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.notification-btn .badge {
    animation: pulse 2s infinite;
}
</style>

<script>
$(document).ready(function() {
    // PWA Install functionality
    let deferredPrompt;
    
    // Check if app is already installed
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
        $('#pwa-install-prompt').hide();
    }
    
    // Listen for the beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        $('#install-pwa-btn').show();
    });
    
    // Handle install button click
    $('#install-pwa-btn').on('click', async function() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            
            if (outcome === 'accepted') {
                $.toast({
                    heading: '{{ _lang("Success") }}',
                    text: '{{ _lang("App installation started!") }}',
                    position: 'top-right',
                    loaderBg: '#28a745',
                    icon: 'success',
                    hideAfter: 3000,
                    stack: 6
                });
                $('#pwa-install-prompt').fadeOut();
            }
            
            deferredPrompt = null;
        } else {
            // Show instructions for manual installation
            $.toast({
                heading: '{{ _lang("Install Instructions") }}',
                text: '{{ _lang("Tap the menu button and select \"Add to Home Screen\"") }}',
                position: 'top-right',
                loaderBg: '#ff6849',
                icon: 'info',
                hideAfter: 5000,
                stack: 6
            });
        }
    });
    
    // Handle dismiss button
    $('#dismiss-pwa-btn').on('click', function() {
        // Set cookie to dismiss prompt for 7 days
        document.cookie = "pwa_dismissed=true; expires=" + new Date(Date.now() + 7*24*60*60*1000).toUTCString() + "; path=/";
        $('#pwa-install-prompt').fadeOut();
    });
    
    // Auto-dismiss install prompt if no install prompt available
    setTimeout(() => {
        if (!deferredPrompt && $('#install-pwa-btn').is(':visible')) {
            $('#pwa-install-prompt').fadeOut();
        }
    }, 10000);
    
    // Add loading states to buttons
    $('.mobile-btn').on('click', function() {
        if (!$(this).hasClass('loading')) {
            $(this).addClass('loading');
            setTimeout(() => {
                $(this).removeClass('loading');
            }, 2000);
        }
    });
    
    // Refresh data on pull down
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
                text: '{{ _lang("Updating your data...") }}',
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
});
</script>
@endsection
