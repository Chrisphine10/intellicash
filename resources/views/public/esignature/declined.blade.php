<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Signing Declined</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .declined-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        .declined-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .declined-icon {
            font-size: 80px;
            color: #dc3545;
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
    <div class="declined-container">
        <div class="declined-card">
            <div class="declined-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            
            <h1 class="text-danger mb-3">Document Signing Declined</h1>
            
            <p class="lead text-muted mb-4">
                You have declined to sign this document. The document sender has been notified of your decision.
            </p>
            
            <div class="document-info">
                <h5><i class="fas fa-file-alt"></i> Document Information</h5>
                <p class="mb-1"><strong>Title:</strong> {{ $signature->document->title }}</p>
                <p class="mb-1"><strong>Declined by:</strong> {{ $signature->signer_name }}</p>
                <p class="mb-1"><strong>Email:</strong> {{ $signature->signer_email }}</p>
                <p class="mb-0"><strong>Declined on:</strong> {{ $signature->updated_at->format('M d, Y H:i:s') }}</p>
            </div>
            
            @if($signature->signature_metadata && isset($signature->signature_metadata['decline_reason']))
                <div class="notice">
                    <h6><i class="fas fa-comment"></i> Reason for Declining</h6>
                    <p class="mb-0">{{ $signature->signature_metadata['decline_reason'] }}</p>
                </div>
            @endif
            
            <div class="notice">
                <h6><i class="fas fa-info-circle"></i> What happens next?</h6>
                <ul class="text-start mb-0">
                    <li>The document sender has been notified of your decision</li>
                    <li>You will not be able to sign this document again</li>
                    <li>If you change your mind, please contact the document sender directly</li>
                </ul>
            </div>
            
            <div class="mt-4">
                <p class="text-muted">
                    <i class="fas fa-envelope"></i>
                    If you have any questions or concerns, please contact the document sender at 
                    <strong>{{ $signature->document->sender_email }}</strong>
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
