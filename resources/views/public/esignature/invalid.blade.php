<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid Signature Request</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .invalid-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }
        .invalid-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .invalid-icon {
            font-size: 80px;
            color: #6c757d;
            margin-bottom: 20px;
        }
        .notice {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="invalid-container">
        <div class="invalid-card">
            <div class="invalid-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <h1 class="text-secondary mb-3">Invalid Signature Request</h1>
            
            <p class="lead text-muted mb-4">
                This signature request is no longer valid or has already been processed.
            </p>
            
            <div class="notice">
                <h6><i class="fas fa-info-circle"></i> Possible reasons:</h6>
                <ul class="text-start mb-0">
                    <li>The document has already been signed</li>
                    <li>The document has been cancelled</li>
                    <li>The signature link has expired</li>
                    <li>The link has been used already</li>
                </ul>
            </div>
            
            <div class="mt-4">
                <p class="text-muted">
                    <i class="fas fa-envelope"></i>
                    If you believe this is an error, please contact the document sender for assistance.
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
