@extends('layouts.app')

@section('title', 'Advanced Loan Management Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Advanced Loan Management</li>
                    </ol>
                </div>
                <h4 class="page-title">Advanced Loan Management Dashboard</h4>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card widget-flat">
                <div class="card-body">
                    <div class="float-right">
                        <i class="mdi mdi-account-multiple widget-icon"></i>
                    </div>
                    <h5 class="text-muted font-weight-normal mt-0" title="Total Loans">Total Loans</h5>
                    <h3 class="mt-3 mb-3">{{ $stats['total_loans'] }}</h3>
                    <p class="mb-0 text-muted">
                        <span class="text-success mr-2"><i class="mdi mdi-arrow-up-bold"></i> 5.27%</span>
                        <span class="text-nowrap">Since last month</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card widget-flat">
                <div class="card-body">
                    <div class="float-right">
                        <i class="mdi mdi-clock-outline widget-icon bg-warning-lighten text-warning"></i>
                    </div>
                    <h5 class="text-muted font-weight-normal mt-0" title="Pending Loans">Pending Loans</h5>
                    <h3 class="mt-3 mb-3">{{ $stats['pending_loans'] }}</h3>
                    <p class="mb-0 text-muted">
                        <span class="text-warning mr-2"><i class="mdi mdi-arrow-up-bold"></i> 1.08%</span>
                        <span class="text-nowrap">Since last month</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card widget-flat">
                <div class="card-body">
                    <div class="float-right">
                        <i class="mdi mdi-check-circle widget-icon bg-success-lighten text-success"></i>
                    </div>
                    <h5 class="text-muted font-weight-normal mt-0" title="Active Loans">Active Loans</h5>
                    <h3 class="mt-3 mb-3">{{ $stats['active_loans'] }}</h3>
                    <p class="mb-0 text-muted">
                        <span class="text-success mr-2"><i class="mdi mdi-arrow-up-bold"></i> 12.38%</span>
                        <span class="text-nowrap">Since last month</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card widget-flat">
                <div class="card-body">
                    <div class="float-right">
                        <i class="mdi mdi-currency-usd widget-icon bg-info-lighten text-info"></i>
                    </div>
                    <h5 class="text-muted font-weight-normal mt-0" title="Total Loan Amount">Total Loan Amount</h5>
                    <h3 class="mt-3 mb-3">KES {{ number_format($stats['total_loan_amount'], 0) }}</h3>
                    <p class="mb-0 text-muted">
                        <span class="text-success mr-2"><i class="mdi mdi-arrow-up-bold"></i> 8.24%</span>
                        <span class="text-nowrap">Since last month</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Loans -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="dropdown float-right">
                        <a href="#" class="dropdown-toggle arrow-none card-drop" data-toggle="dropdown" aria-expanded="false">
                            <i class="mdi mdi-dots-vertical"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a href="{{ route('loans.filter', 'pending') }}" class="dropdown-item">View Pending Loans</a>
                            <a href="{{ route('loans.filter', 'active') }}" class="dropdown-item">View Active Loans</a>
                            <a href="{{ route('loan_products.index') }}" class="dropdown-item">Manage Loan Products</a>
                        </div>
                    </div>

                    <h4 class="header-title mb-3">Recent Loans</h4>

                    <div class="table-responsive">
                        <table class="table table-sm table-centered mb-0 font-14">
                            <thead class="thead-light">
                                <tr>
                                    <th>Loan ID</th>
                                    <th>Borrower</th>
                                    <th>Amount</th>
                                    <th>Product</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentLoans as $loan)
                                    <tr>
                                        <td>
                                            <a href="{{ route('loans.show', $loan->id) }}" class="text-primary">
                                                {{ $loan->loan_id }}
                                            </a>
                                        </td>
                                        <td>
                                            <div>
                                                <h6 class="m-0 font-14">{{ $loan->borrower->first_name }} {{ $loan->borrower->last_name }}</h6>
                                                <p class="m-0 text-muted">{{ $loan->borrower->email }}</p>
                                            </div>
                                        </td>
                                        <td>{{ currency_symbol() }} {{ number_format($loan->applied_amount, 0) }}</td>
                                        <td>{{ $loan->loanProduct->name }}</td>
                                        <td>
                                            @if($loan->status == 0)
                                                <span class="badge badge-warning">Pending</span>
                                            @elseif($loan->status == 1)
                                                <span class="badge badge-success">Active</span>
                                            @elseif($loan->status == 2)
                                                <span class="badge badge-info">Completed</span>
                                            @elseif($loan->status == 3)
                                                <span class="badge badge-danger">Cancelled</span>
                                            @endif
                                        </td>
                                        <td>{{ $loan->created_at->format('M d, Y') }}</td>
                                        <td>
                                            <a href="{{ route('loans.show', $loan->id) }}" class="btn btn-sm btn-primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No loans found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Loan Products -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="dropdown float-right">
                        <a href="#" class="dropdown-toggle arrow-none card-drop" data-toggle="dropdown" aria-expanded="false">
                            <i class="mdi mdi-dots-vertical"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a href="{{ route('loan_products.index') }}" class="dropdown-item">View All Products</a>
                            <a href="{{ route('loan_products.create') }}" class="dropdown-item">Create New Product</a>
                        </div>
                    </div>

                    <h4 class="header-title mb-3">Active Loan Products</h4>

                    @forelse($activeProducts as $product)
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <div class="avatar-sm rounded-circle bg-primary-lighten">
                                    <span class="avatar-title rounded-circle bg-primary text-white font-16">
                                        {{ substr($product->name, 0, 1) }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="m-0 font-14">{{ $product->name }}</h6>
                                <p class="text-muted mb-1">KES {{ number_format($product->minimum_amount, 0) }} - KES {{ number_format($product->maximum_amount, 0) }}</p>
                                <small class="text-muted">{{ $product->advancedLoanApplications()->count() }} applications</small>
                            </div>
                            <div class="flex-shrink-0">
                                <a href="{{ route('loan_products.edit', $product->id) }}" class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4">
                            <i class="mdi mdi-information-outline text-muted" style="font-size: 48px;"></i>
                            <p class="text-muted mt-2">No active loan products found</p>
                            <a href="{{ route('loan_products.create') }}" class="btn btn-primary btn-sm">
                                Create First Product
                            </a>
                        </div>
                    @endforelse
                </div>
            </div>


            <!-- Quick Actions -->
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title mb-3">Quick Actions</h4>
                    
                    <div class="d-grid gap-2">
                        <a href="{{ route('loan_products.create') }}" class="btn btn-primary">
                            <i class="mdi mdi-plus"></i> Create Loan Product
                        </a>
                        <a href="{{ route('loans.filter', 'pending') }}" class="btn btn-outline-primary">
                            <i class="mdi mdi-format-list-bulleted"></i> Manage Pending Loans
                        </a>
                        <a href="{{ route('loan_terms.create') }}" class="btn btn-outline-success">
                            <i class="mdi mdi-file-document"></i> Create Terms & Privacy
                        </a>
                        <a href="{{ route('loan_terms.create_from_template') }}" class="btn btn-outline-info">
                            <i class="mdi mdi-file-document-edit"></i> Create from Template
                        </a>
                        <a href="{{ route('legal_templates.index') }}" class="btn btn-outline-warning">
                            <i class="mdi mdi-cog"></i> Manage Templates
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Auto-refresh dashboard every 5 minutes
    setInterval(function() {
        location.reload();
    }, 300000);
});
</script>
@endpush
