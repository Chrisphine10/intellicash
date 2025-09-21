@extends('layouts.app')

@section('title', 'Check Application Status')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white text-center">
                    <i class="fas fa-search fa-2x mb-3"></i>
                    <h3 class="mb-0">Check Application Status</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('loan_application.status') }}">
                        <div class="form-group">
                            <label for="application_number">Application Number</label>
                            <div class="input-group">
                                <input type="text" name="application_number" id="application_number" 
                                       class="form-control" placeholder="Enter your application number"
                                       value="{{ request('application_number') }}" required>
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Check Status
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    @if($application)
                        <div class="mt-4">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-file-alt"></i> Application Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Application Number:</strong> {{ $application->application_number }}</p>
                                            <p><strong>Applicant Name:</strong> {{ $application->applicant_name }}</p>
                                            <p><strong>Business Name:</strong> {{ $application->business_name }}</p>
                                            <p><strong>Product Type:</strong> {{ $application->application_type_label }}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Requested Amount:</strong> KES {{ number_format($application->requested_amount, 0) }}</p>
                                            <p><strong>Application Date:</strong> {{ $application->application_date->format('F d, Y') }}</p>
                                            <p><strong>Current Status:</strong> 
                                                <span class="badge badge-{{ $application->status === 'approved' ? 'success' : ($application->status === 'rejected' ? 'danger' : 'warning') }}">
                                                    {{ $application->status_label }}
                                                </span>
                                            </p>
                                            @if($application->approved_amount)
                                                <p><strong>Approved Amount:</strong> KES {{ number_format($application->approved_amount, 0) }}</p>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Status Timeline -->
                                    <div class="mt-4">
                                        <h6><i class="fas fa-timeline"></i> Application Progress</h6>
                                        <div class="timeline">
                                            <div class="timeline-item {{ $application->created_at ? 'completed' : '' }}">
                                                <div class="timeline-marker bg-success"></div>
                                                <div class="timeline-content">
                                                    <h6>Application Submitted</h6>
                                                    <p class="text-muted">Your application was received and is being processed</p>
                                                    <small class="text-muted">{{ $application->created_at ? $application->created_at->format('M d, Y g:i A') : '' }}</small>
                                                </div>
                                            </div>

                                            <div class="timeline-item {{ in_array($application->status, ['under_review', 'approved', 'rejected', 'disbursed']) ? 'completed' : '' }}">
                                                <div class="timeline-marker {{ in_array($application->status, ['under_review', 'approved', 'rejected', 'disbursed']) ? 'bg-success' : 'bg-secondary' }}"></div>
                                                <div class="timeline-content">
                                                    <h6>Under Review</h6>
                                                    <p class="text-muted">Your application is being reviewed by our team</p>
                                                    @if($application->reviewed_at)
                                                        <small class="text-muted">{{ $application->reviewed_at->format('M d, Y g:i A') }}</small>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="timeline-item {{ in_array($application->status, ['approved', 'rejected', 'disbursed']) ? 'completed' : '' }}">
                                                <div class="timeline-marker {{ in_array($application->status, ['approved', 'rejected', 'disbursed']) ? 'bg-success' : 'bg-secondary' }}"></div>
                                                <div class="timeline-content">
                                                    <h6>Decision Made</h6>
                                                    <p class="text-muted">
                                                        @if($application->status === 'approved')
                                                            Your application has been approved!
                                                        @elseif($application->status === 'rejected')
                                                            Unfortunately, your application was not approved.
                                                        @else
                                                            Decision is being finalized
                                                        @endif
                                                    </p>
                                                    @if($application->approved_at)
                                                        <small class="text-muted">{{ $application->approved_at->format('M d, Y g:i A') }}</small>
                                                    @endif
                                                </div>
                                            </div>

                                            @if($application->status === 'disbursed')
                                                <div class="timeline-item completed">
                                                    <div class="timeline-marker bg-success"></div>
                                                    <div class="timeline-content">
                                                        <h6>Loan Disbursed</h6>
                                                        <p class="text-muted">Your loan has been disbursed to your account</p>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Additional Information -->
                                    @if($application->review_notes)
                                        <div class="mt-4">
                                            <h6><i class="fas fa-sticky-note"></i> Review Notes</h6>
                                            <div class="alert alert-info">
                                                {{ $application->review_notes }}
                                            </div>
                                        </div>
                                    @endif

                                    @if($application->rejection_reason)
                                        <div class="mt-4">
                                            <h6><i class="fas fa-exclamation-triangle"></i> Rejection Reason</h6>
                                            <div class="alert alert-danger">
                                                {{ $application->rejection_reason }}
                                            </div>
                                        </div>
                                    @endif

                                    @if($application->status === 'approved' && !$application->loan)
                                        <div class="mt-4">
                                            <div class="alert alert-success">
                                                <h6><i class="fas fa-check-circle"></i> Congratulations!</h6>
                                                <p>Your loan application has been approved. You will be contacted shortly for loan disbursement.</p>
                                            </div>
                                        </div>
                                    @endif

                                    @if($application->status === 'disbursed' && $application->loan)
                                        <div class="mt-4">
                                            <div class="alert alert-success">
                                                <h6><i class="fas fa-money-bill-wave"></i> Loan Disbursed</h6>
                                                <p>Your loan has been successfully disbursed. Loan ID: <strong>{{ $application->loan->loan_id }}</strong></p>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @elseif(request('application_number'))
                        <div class="mt-4">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                No application found with the number: <strong>{{ request('application_number') }}</strong>
                            </div>
                        </div>
                    @endif

                    <div class="mt-4 text-center">
                        <a href="{{ route('loan_application.form') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Apply for New Loan
                        </a>
                    </div>

                    <div class="mt-4">
                        <h6><i class="fas fa-question-circle"></i> Need Help?</h6>
                        <p class="text-muted">
                            If you have any questions about your application status, please contact us:<br>
                            <strong>Phone:</strong> +254 700 000 000<br>
                            <strong>Email:</strong> support@example.com
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border-radius: 15px;
}

.card-header {
    border-radius: 15px 15px 0 0 !important;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #6c757d;
}

.timeline-item.completed .timeline-marker {
    background: #28a745;
}

.timeline-content {
    padding-left: 20px;
}

.timeline-content h6 {
    margin-bottom: 5px;
    font-weight: 600;
}

.btn {
    border-radius: 25px;
    padding: 10px 25px;
}

.alert {
    border-radius: 10px;
    border: none;
}
</style>
@endsection
