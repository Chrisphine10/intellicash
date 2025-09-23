<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guarantee Declined</title>
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
            background: linear-gradient(45deg, #ffc107, #fd7e14);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 2rem;
        }
        .decline-icon {
            font-size: 4rem;
            color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h1 class="mb-0"><i class="fas fa-times-circle"></i> Guarantee Declined</h1>
                    </div>
                    <div class="card-body p-4 text-center">
                        <div class="decline-icon mb-4">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        
                        <h4 class="text-warning mb-3">Guarantee Declined</h4>
                        
                        <p class="lead">You have declined to be a guarantor for <strong>{{ $guarantorRequest->borrower->first_name }} {{ $guarantorRequest->borrower->last_name }}</strong>'s loan application.</p>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> What happens next?</h6>
                            <ul class="list-unstyled mb-0 text-start">
                                <li><i class="fas fa-arrow-right text-primary"></i> The borrower will be notified of your decision</li>
                                <li><i class="fas fa-arrow-right text-primary"></i> They may need to find another guarantor</li>
                                <li><i class="fas fa-arrow-right text-primary"></i> The loan application may be delayed or withdrawn</li>
                            </ul>
                        </div>
                        
                        <p class="text-muted">Thank you for your consideration. You can now close this window.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
