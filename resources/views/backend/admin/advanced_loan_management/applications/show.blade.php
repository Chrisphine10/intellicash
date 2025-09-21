@extends('layouts.app')

@section('title', 'Application Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('advanced_loan_management.index') }}">Advanced Loan Management</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('advanced_loan_management.applications') }}">Applications</a></li>
                        <li class="breadcrumb-item active">Application Details</li>
                    </ol>
                </div>
                <h4 class="page-title">Application Details - {{ $application->application_number }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Application Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Application Number:</strong> {{ $application->application_number }}</p>
                            <p><strong>Applicant Name:</strong> {{ $application->applicant_name }}</p>
                            <p><strong>Applicant Email:</strong> {{ $application->applicant_email }}</p>
                            <p><strong>Applicant Phone:</strong> {{ $application->applicant_phone }}</p>
                            <p><strong>Business Name:</strong> {{ $application->business_name }}</p>
                            <p><strong>Business Type:</strong> {{ $application->business_type_label }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Requested Amount:</strong> KES {{ number_format($application->requested_amount, 0) }}</p>
                            <p><strong>Approved Amount:</strong> {{ $application->approved_amount ? 'KES ' . number_format($application->approved_amount, 0) : 'Not approved yet' }}</p>
                            <p><strong>Application Type:</strong> {{ $application->application_type_label }}</p>
                            <p><strong>Status:</strong> 
                                <span class="badge badge-{{ $application->status === 'approved' ? 'success' : ($application->status === 'rejected' ? 'danger' : 'warning') }}">
                                    {{ $application->status_label }}
                                </span>
                            </p>
                            <p><strong>Application Date:</strong> {{ $application->application_date->format('F d, Y') }}</p>
                            <p><strong>Loan Product:</strong> {{ $application->loanProduct->name }}</p>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12">
                            <h6><strong>Loan Purpose:</strong></h6>
                            <p>{{ $application->loan_purpose }}</p>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12">
                            <h6><strong>Business Description:</strong></h6>
                            <p>{{ $application->business_description }}</p>
                        </div>
                    </div>

                    @if($application->collateral_description)
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6><strong>Collateral Description:</strong></h6>
                            <p>{{ $application->collateral_description }}</p>
                        </div>
                    </div>
                    @endif

                    @if($application->review_notes)
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6><strong>Review Notes:</strong></h6>
                            <p>{{ $application->review_notes }}</p>
                        </div>
                    </div>
                    @endif

                    @if($application->rejection_reason)
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6><strong>Rejection Reason:</strong></h6>
                            <p class="text-danger">{{ $application->rejection_reason }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    @if($application->canBeApproved())
                        <button class="btn btn-success btn-block mb-2 approve-application" data-id="{{ $application->id }}">
                            <i class="fas fa-check"></i> Approve Application
                        </button>
                    @endif

                    @if($application->canBeRejected())
                        <button class="btn btn-danger btn-block mb-2 reject-application" data-id="{{ $application->id }}">
                            <i class="fas fa-times"></i> Reject Application
                        </button>
                    @endif

                    @if($application->status === 'approved' && !$application->loan)
                        <button class="btn btn-primary btn-block mb-2 disburse-loan" data-id="{{ $application->id }}">
                            <i class="fas fa-money-bill-wave"></i> Disburse Loan
                        </button>
                    @endif

                    <a href="{{ route('advanced_loan_management.applications.edit', $application->id) }}" class="btn btn-info btn-block mb-2">
                        <i class="fas fa-edit"></i> Edit Application
                    </a>

                    <a href="{{ route('advanced_loan_management.applications') }}" class="btn btn-secondary btn-block">
                        <i class="fas fa-arrow-left"></i> Back to Applications
                    </a>
                </div>
            </div>

            @if($application->loan)
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Loan Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Loan ID:</strong> {{ $application->loan->loan_id }}</p>
                    <p><strong>Total Payable:</strong> KES {{ number_format($application->loan->total_payable, 0) }}</p>
                    <p><strong>Total Paid:</strong> KES {{ number_format($application->loan->total_paid, 0) }}</p>
                    <p><strong>Status:</strong> 
                        <span class="badge badge-{{ $application->loan->status == 2 ? 'success' : 'warning' }}">
                            {{ $application->loan->status == 2 ? 'Active' : 'Pending' }}
                        </span>
                    </p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
