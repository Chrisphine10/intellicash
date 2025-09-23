<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation Expired</title>
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
            background: linear-gradient(45deg, #6c757d, #495057);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 2rem;
        }
        .expired-icon {
            font-size: 4rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h1 class="mb-0"><i class="fas fa-clock"></i> Invitation Expired</h1>
                    </div>
                    <div class="card-body p-4 text-center">
                        <div class="expired-icon mb-4">
                            <i class="fas fa-clock"></i>
                        </div>
                        
                        <h4 class="text-muted mb-3">Invitation No Longer Valid</h4>
                        
                        <p class="lead">This guarantor invitation has expired or is no longer valid.</p>
                        
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle"></i> Possible reasons:</h6>
                            <ul class="list-unstyled mb-0 text-start">
                                <li><i class="fas fa-arrow-right text-warning"></i> The invitation has expired (7 days)</li>
                                <li><i class="fas fa-arrow-right text-warning"></i> The loan application has been withdrawn</li>
                                <li><i class="fas fa-arrow-right text-warning"></i> The invitation has already been responded to</li>
                            </ul>
                        </div>
                        
                        <p class="text-muted">If you believe this is an error, please contact the borrower or our support team.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
