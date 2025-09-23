<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document Signed Successfully</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #28a745;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .document-info {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        .download-button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 20px 0;
        }
        .download-button:hover {
            background-color: #0056b3;
        }
        .signature-summary {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>✅ Document Signed Successfully</h1>
        <p>All signatures have been completed</p>
    </div>
    
    <div class="content">
        <div class="document-info">
            <h3>{{ $document->title }}</h3>
            @if($document->description)
                <p><strong>Description:</strong> {{ $document->description }}</p>
            @endif
            <p><strong>Document Type:</strong> {{ ucfirst($document->document_type) }}</p>
            <p><strong>Completed:</strong> {{ $completedAt->format('M d, Y H:i:s') }}</p>
        </div>
        
        <div class="signature-summary">
            <h4>📋 Signature Summary:</h4>
            <p><strong>Total Signers:</strong> {{ $signerCount }}</p>
            <p><strong>Status:</strong> All signatures completed</p>
            <p><strong>Completion Time:</strong> {{ $completedAt->format('M d, Y H:i:s') }}</p>
        </div>
        
        <div style="text-align: center;">
            <a href="{{ $downloadUrl }}" class="download-button">
                📥 Download Signed Document
            </a>
        </div>
        
        <p><strong>Important:</strong> The signed document is now legally binding. Please save a copy for your records.</p>
        
        <div class="footer">
            <p>This email was sent by IntelliCash E-Signature System.</p>
            <p>The signed document has been securely stored and is available for download.</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
