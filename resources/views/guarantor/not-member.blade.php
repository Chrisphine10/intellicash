<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Not a Member</title>
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
            background: linear-gradient(45deg, #dc3545, #c82333);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 2rem;
        }
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h1 class="mb-0"><i class="fas fa-user-times"></i> Not a Member</h1>
                    </div>
                    <div class="card-body p-4 text-center">
                        <div class="error-icon mb-4">
                            <i class="fas fa-user-times"></i>
                        </div>
                        
                        <h4 class="text-danger mb-3">Membership Required</h4>
                        
                        <p class="lead">You must be a member of <strong>{{ app('tenant')->name ?? 'this organization' }}</strong> to be a guarantor.</p>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> To become a guarantor:</h6>
                            <ul class="list-unstyled mb-0 text-start">
                                <li><i class="fas fa-arrow-right text-primary"></i> You must first become a member of the organization</li>
                                <li><i class="fas fa-arrow-right text-primary"></i> Contact the organization to join</li>
                                <li><i class="fas fa-arrow-right text-primary"></i> Once you're a member, you can be a guarantor</li>
                            </ul>
                        </div>
                        
                        <p class="text-muted">Please contact the borrower or our support team for more information about becoming a member.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
