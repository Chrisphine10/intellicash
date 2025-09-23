<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Signed Successfully</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .success-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .success-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .signature-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .security-notice {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <h1 class="text-success mb-3">Document Signed Successfully!</h1>
            
            <p class="lead text-muted mb-4">
                Thank you for signing the document. Your signature has been recorded and the document is now legally binding.
            </p>
            
            <div class="signature-info">
                <h5><i class="fas fa-file-signature"></i> Document Information</h5>
                <p class="mb-1"><strong>Title:</strong> {{ $signature->document->title }}</p>
                <p class="mb-1"><strong>Signed by:</strong> {{ $signature->signer_name }}</p>
                <p class="mb-1"><strong>Email:</strong> {{ $signature->signer_email }}</p>
                <p class="mb-0"><strong>Signed on:</strong> {{ $signature->signed_at->format('M d, Y H:i:s') }}</p>
            </div>
            
            <div class="security-notice">
                <h6><i class="fas fa-shield-alt"></i> Security Information</h6>
                <ul class="text-start mb-0">
                    <li>Your signature has been securely recorded</li>
                    <li>IP Address: {{ $signature->ip_address }}</li>
                    <li>Browser: {{ $signature->browser_info }}</li>
                    <li>Device: {{ $signature->device_info }}</li>
                    <li>Signature Type: {{ ucfirst($signature->signature_type) }}</li>
                </ul>
            </div>
            
            <div class="mt-4">
                <p class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    The document sender will be notified of the completion and can download the signed document.
                </p>
            </div>
            
            <div class="mt-4">
                <button class="btn btn-outline-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Confirmation
                </button>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
