<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document for Signature</title>
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
            background-color: #007bff;
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
            border-left: 4px solid #007bff;
        }
        .sign-button {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 20px 0;
        }
        .sign-button:hover {
            background-color: #218838;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #6c757d;
        }
        .security-notice {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📄 Document for Signature</h1>
        <p>You have received a document that requires your signature</p>
    </div>
    
    <div class="content">
        @if($senderCompany)
            <p><strong>From:</strong> {{ $senderName }} ({{ $senderCompany }})</p>
        @else
            <p><strong>From:</strong> {{ $senderName }}</p>
        @endif
        
        <div class="document-info">
            <h3>{{ $document->title }}</h3>
            @if($document->description)
                <p><strong>Description:</strong> {{ $document->description }}</p>
            @endif
            <p><strong>Document Type:</strong> {{ ucfirst($document->document_type) }}</p>
            <p><strong>File:</strong> {{ $document->file_name }}</p>
            @if($expiresAt)
                <p><strong>Expires:</strong> {{ $expiresAt->format('M d, Y H:i') }}</p>
            @endif
        </div>
        
        @if($customMessage)
            <div class="security-notice">
                <h4>📝 Message from {{ $senderName }}:</h4>
                <p>{{ $customMessage }}</p>
            </div>
        @endif
        
        <div style="text-align: center;">
            <a href="{{ $signingUrl }}" class="sign-button">
                ✍️ Sign Document Now
            </a>
        </div>
        
        <div class="security-notice">
            <h4>🔒 Security Information:</h4>
            <ul>
                <li>This document is legally binding once signed</li>
                <li>Your signature and IP address will be recorded</li>
                <li>Do not share this link with others</li>
                <li>If you did not expect this document, please contact the sender</li>
            </ul>
        </div>
        
        <p><strong>Important:</strong> Please review the document carefully before signing. Once signed, the document becomes legally binding.</p>
        
        @if($expiresAt)
            <p><strong>Deadline:</strong> This document expires on {{ $expiresAt->format('M d, Y H:i') }}. Please sign before this date.</p>
        @endif
        
        <div class="footer">
            <p>This email was sent by {{ $senderCompany ?: 'IntelliCash E-Signature System' }}.</p>
            <p>If you have any questions about this document, please contact {{ $senderName }} at {{ $document->sender_email }}.</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
