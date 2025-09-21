@extends('layouts.app')

@section('title', 'Application Submitted Successfully')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-lg border-success">
                <div class="card-header bg-success text-white text-center">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h3 class="mb-0">Application Submitted Successfully!</h3>
                </div>
                <div class="card-body text-center">
                    <div class="alert alert-success">
                        <h5><i class="fas fa-info-circle"></i> Application Details</h5>
                        <p class="mb-2"><strong>Application Number:</strong> <span class="text-primary">{{ $application->application_number }}</span></p>
                        <p class="mb-2"><strong>Product:</strong> {{ $application->loanProduct->name }}</p>
                        <p class="mb-2"><strong>Requested Amount:</strong> KES {{ number_format($application->requested_amount, 0) }}</p>
                        <p class="mb-2"><strong>Application Date:</strong> {{ $application->application_date->format('F d, Y') }}</p>
                        <p class="mb-0"><strong>Status:</strong> <span class="badge badge-warning">{{ $application->status_label }}</span></p>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-primary">
                                <div class="card-body">
                                    <h6 class="card-title text-primary"><i class="fas fa-clock"></i> What Happens Next?</h6>
                                    <ul class="list-unstyled text-left">
                                        <li><i class="fas fa-check text-success"></i> Application received</li>
                                        <li><i class="fas fa-hourglass-half text-warning"></i> Under review (2-3 business days)</li>
                                        <li><i class="fas fa-hourglass-half text-warning"></i> Credit assessment</li>
                                        <li><i class="fas fa-hourglass-half text-warning"></i> Final decision</li>
                                        <li><i class="fas fa-hourglass-half text-warning"></i> Loan disbursement (if approved)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-info">
                                <div class="card-body">
                                    <h6 class="card-title text-info"><i class="fas fa-envelope"></i> Communication</h6>
                                    <ul class="list-unstyled text-left">
                                        <li><i class="fas fa-check text-success"></i> Email confirmation sent</li>
                                        <li><i class="fas fa-phone text-primary"></i> Phone call for verification</li>
                                        <li><i class="fas fa-envelope text-primary"></i> Email updates on progress</li>
                                        <li><i class="fas fa-sms text-primary"></i> SMS notifications</li>
                                        <li><i class="fas fa-file-alt text-primary"></i> Final decision letter</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4">
                        <h6><i class="fas fa-info-circle"></i> Important Information</h6>
                        <ul class="mb-0 text-left">
                            <li>Keep your application number safe: <strong>{{ $application->application_number }}</strong></li>
                            <li>Check your email regularly for updates</li>
                            <li>Ensure your phone is available for verification calls</li>
                            <li>You can track your application status using the application number</li>
                            <li>Processing time is typically 2-3 business days</li>
                        </ul>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('loan_application.status') }}" class="btn btn-primary mr-3">
                            <i class="fas fa-search"></i> Track Application Status
                        </a>
                        <a href="{{ route('loan_application.form') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-plus"></i> Apply for Another Loan
                        </a>
                    </div>

                    <div class="mt-4">
                        <h6>Need Help?</h6>
                        <p class="text-muted">
                            If you have any questions about your application, please contact us:<br>
                            <strong>Phone:</strong> +254 700 000 000<br>
                            <strong>Email:</strong> support@example.com<br>
                            <strong>Hours:</strong> Monday - Friday, 8:00 AM - 5:00 PM
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

.alert {
    border-radius: 10px;
    border: none;
}

.btn {
    border-radius: 25px;
    padding: 10px 25px;
}

.list-unstyled li {
    margin-bottom: 8px;
}

.fa-check {
    color: #28a745 !important;
}

.fa-hourglass-half {
    color: #ffc107 !important;
}

.fa-phone, .fa-envelope, .fa-sms, .fa-file-alt {
    color: #007bff !important;
}
</style>
@endsection
