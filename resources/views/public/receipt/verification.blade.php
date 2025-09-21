<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tenant ? $tenant->name . ' - ' : '' }}Receipt Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .verification-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: none;
        }
        .verification-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .verification-body {
            padding: 2rem;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
        }
        .transaction-detail {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #007bff;
        }
        .tenant-logo {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .verification-icon {
            font-size: 3rem;
            color: #28a745;
        }
        .error-icon {
            font-size: 3rem;
            color: #dc3545;
        }
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #6c757d;
        }
        .qr-scan-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card verification-card">
                    @if($error)
                        <!-- Error State -->
                        <div class="verification-header">
                            <div class="tenant-logo">
                                <i class="fas fa-exclamation-triangle error-icon"></i>
                            </div>
                            <h2 class="mb-0">Verification Failed</h2>
                            <p class="mb-0 mt-2">Unable to verify this receipt</p>
                        </div>
                        <div class="verification-body">
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                {{ $error }}
                            </div>
                            <div class="text-center">
                                <p class="text-muted">
                                    This could be due to:
                                </p>
                                <ul class="list-unstyled text-muted">
                                    <li><i class="fas fa-times text-danger me-2"></i>Invalid or expired QR code</li>
                                    <li><i class="fas fa-times text-danger me-2"></i>Corrupted receipt data</li>
                                    <li><i class="fas fa-times text-danger me-2"></i>Network connectivity issues</li>
                                </ul>
                            </div>
                        </div>
                    @else
                        <!-- Success State -->
                        <div class="verification-header">
                            <div class="tenant-logo">
                                <i class="fas fa-check-circle verification-icon"></i>
                            </div>
                            <h2 class="mb-0">Receipt Verified</h2>
                            <p class="mb-0 mt-2">{{ $tenant->name ?? 'Transaction Verified' }}</p>
                        </div>
                        <div class="verification-body">
                            <!-- Transaction Status -->
                            <div class="text-center mb-4">
                                <span class="badge status-badge bg-success">
                                    <i class="fas fa-check me-1"></i>
                                    {{ ucfirst($transaction['status']) }}
                                </span>
                            </div>

                            <!-- Transaction Details -->
                            <div class="transaction-detail">
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Transaction ID</strong>
                                    </div>
                                    <div class="col-6 text-end">
                                        #{{ $transaction['transaction_id'] }}
                                    </div>
                                </div>
                            </div>

                            <div class="transaction-detail">
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Amount</strong>
                                    </div>
                                    <div class="col-6 text-end">
                                        <span class="h5 text-primary mb-0">
                                            {{ number_format($transaction['amount'], 2) }} {{ $transaction['currency'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="transaction-detail">
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Type</strong>
                                    </div>
                                    <div class="col-6 text-end">
                                        {{ ucfirst($transaction['type']) }}
                                    </div>
                                </div>
                            </div>

                            <div class="transaction-detail">
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Date & Time</strong>
                                    </div>
                                    <div class="col-6 text-end">
                                        {{ $transaction['created_at'] }}
                                    </div>
                                </div>
                            </div>

                            <div class="transaction-detail">
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Verified At</strong>
                                    </div>
                                    <div class="col-6 text-end">
                                        {{ $transaction['verified_at'] }}
                                    </div>
                                </div>
                            </div>

                            <!-- QR Code Info -->
                            <div class="qr-scan-info">
                                <div class="text-center">
                                    <i class="fas fa-qrcode text-primary mb-2"></i>
                                    <h6 class="mb-1">QR Code Verification</h6>
                                    <p class="small mb-0 text-muted">
                                        This receipt was verified using a secure QR code containing encrypted transaction data.
                                    </p>
                                </div>
                            </div>

                            <!-- Security Notice -->
                            <div class="alert alert-info mt-3" role="alert">
                                <i class="fas fa-shield-alt me-2"></i>
                                <strong>Security Notice:</strong> This is a public verification page. Only basic transaction details are shown for security purposes.
                            </div>
                        </div>
                    @endif

                    <!-- Footer -->
                    <div class="footer">
                        <p class="small">
                            <i class="fas fa-lock me-1"></i>
                            Secured by {{ $tenant->name ?? 'IntelliCash' }} • 
                            <a href="#" class="text-decoration-none">Privacy Policy</a> • 
                            <a href="#" class="text-decoration-none">Terms of Service</a>
                        </p>
                        <p class="small text-muted">
                            Verification powered by blockchain technology
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
