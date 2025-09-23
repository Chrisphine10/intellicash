<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Expired</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .expired-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ffa726 0%, #ff7043 100%);
        }
        .expired-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .expired-icon {
            font-size: 80px;
            color: #ff9800;
            margin-bottom: 20px;
        }
        .document-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .notice {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="expired-container">
        <div class="expired-card">
            <div class="expired-icon">
                <i class="fas fa-clock"></i>
            </div>
            
            <h1 class="text-warning mb-3">Document Expired</h1>
            
            <p class="lead text-muted mb-4">
                This document signing request has expired and is no longer available for signing.
            </p>
            
            <div class="document-info">
                <h5><i class="fas fa-file-alt"></i> Document Information</h5>
                <p class="mb-1"><strong>Title:</strong> {{ $signature->document->title }}</p>
                <p class="mb-1"><strong>Expired on:</strong> {{ $signature->expires_at->format('M d, Y H:i:s') }}</p>
                <p class="mb-1"><strong>Current time:</strong> {{ now()->format('M d, Y H:i:s') }}</p>
            </div>
            
            <div class="notice">
                <h6><i class="fas fa-info-circle"></i> What does this mean?</h6>
                <ul class="text-start mb-0">
                    <li>The document signing deadline has passed</li>
                    <li>You can no longer sign this document through this link</li>
                    <li>The document sender has been notified of the expiration</li>
                </ul>
            </div>
            
            <div class="mt-4">
                <p class="text-muted">
                    <i class="fas fa-envelope"></i>
                    If you still need to sign this document, please contact the document sender at 
                    <strong>{{ $signature->document->sender_email }}</strong> to request a new signing link.
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
