<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guarantor Invitation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8fff8, #e8f5e8);
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 2rem;
        }
        .btn-accept {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-decline {
            background: linear-gradient(45deg, #dc3545, #c82333);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
        }
        .loan-details {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 1.5rem;
            border-radius: 8px;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header text-center">
                        <h1 class="mb-0"><i class="fas fa-handshake"></i> Guarantor Invitation</h1>
                        <p class="mb-0 mt-2">You have been invited to be a guarantor</p>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle"></i> Invitation Details</h5>
                            <p class="mb-0"><strong>{{ $guarantorRequest->borrower->first_name }} {{ $guarantorRequest->borrower->last_name }}</strong> has requested you to be their guarantor for a loan application.</p>
                        </div>

                        @if($guarantorRequest->guarantor_message)
                        <div class="alert alert-light">
                            <h6><i class="fas fa-quote-left"></i> Personal Message</h6>
                            <p class="mb-0 fst-italic">"{{ $guarantorRequest->guarantor_message }}"</p>
                        </div>
                        @endif

                        <div class="loan-details mb-4">
                            <h5><i class="fas fa-file-invoice-dollar"></i> Loan Details</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Borrower:</strong> {{ $guarantorRequest->borrower->first_name }} {{ $guarantorRequest->borrower->last_name }}</p>
                                    <p><strong>Loan Amount:</strong> {{ number_format($guarantorRequest->loan->applied_amount, 2) }} {{ $guarantorRequest->loan->currency->name }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Application Date:</strong> {{ $guarantorRequest->loan->created_at->format('M d, Y') }}</p>
                                    <p><strong>Expires:</strong> {{ $guarantorRequest->expires_at->format('M d, Y \a\t g:i A') }}</p>
                                </div>
                            </div>
                            @if($guarantorRequest->loan->comprehensive_data)
                                @php
                                    $comprehensiveData = json_decode($guarantorRequest->loan->comprehensive_data, true);
                                @endphp
                                @if(isset($comprehensiveData['loan_purpose']))
                                <p><strong>Purpose:</strong> {{ $comprehensiveData['loan_purpose'] }}</p>
                                @endif
                            @endif
                        </div>

                        <div class="warning-box mb-4">
                            <h6><i class="fas fa-exclamation-triangle"></i> Important Notice</h6>
                            <p class="mb-0">As a guarantor, you will be responsible for repaying this loan if the borrower defaults. Please consider this carefully before accepting. This is a legally binding commitment.</p>
                        </div>

                        <form method="POST" action="{{ route('guarantor.accept', ['token' => $guarantorRequest->token]) }}">
                            @csrf
                            <div class="mb-3">
                                <label for="response_message" class="form-label">Response Message (Optional)</label>
                                <textarea class="form-control" id="response_message" name="response_message" rows="3" placeholder="Add a personal message to the borrower..."></textarea>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-accept text-white me-3">
                                    <i class="fas fa-check"></i> Accept Guarantee
                                </button>
                                <a href="{{ route('guarantor.decline', ['token' => $guarantorRequest->token]) }}" class="btn btn-decline text-white">
                                    <i class="fas fa-times"></i> Decline
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
