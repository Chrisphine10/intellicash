<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guarantee Accepted</title>
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
        .success-icon {
            font-size: 4rem;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h1 class="mb-0"><i class="fas fa-check-circle"></i> Guarantee Accepted</h1>
                    </div>
                    <div class="card-body p-4 text-center">
                        <div class="success-icon mb-4">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        
                        <h4 class="text-success mb-3">Thank You!</h4>
                        
                        <p class="lead">You have successfully accepted to be a guarantor for <strong>{{ $guarantorRequest->borrower->first_name }} {{ $guarantorRequest->borrower->last_name }}</strong>'s loan application.</p>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> What happens next?</h6>
                            <ul class="list-unstyled mb-0 text-start">
                                <li><i class="fas fa-arrow-right text-primary"></i> The borrower will be notified of your acceptance</li>
                                <li><i class="fas fa-arrow-right text-primary"></i> The loan application will proceed for review</li>
                                <li><i class="fas fa-arrow-right text-primary"></i> You will be notified of the loan status updates</li>
                            </ul>
                        </div>
                        
                        <p class="text-muted">You can now close this window. Thank you for your support!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
