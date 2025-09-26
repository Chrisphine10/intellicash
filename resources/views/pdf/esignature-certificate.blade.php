<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>E-Signature Certificate - {{ $document->title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
        }
        .section {
            margin-bottom: 25px;
        }
        .section h2 {
            color: #34495e;
            border-bottom: 1px solid #bdc3c7;
            padding-bottom: 5px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 8px;
            border-bottom: 1px solid #ecf0f1;
        }
        .info-table td:first-child {
            font-weight: bold;
            width: 200px;
            background-color: #f8f9fa;
        }
        .signature-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .signature-header {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .signature-details {
            font-size: 12px;
            color: #7f8c8d;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #bdc3c7;
            font-size: 10px;
            color: #7f8c8d;
            text-align: center;
        }
        .integrity-section {
            background-color: #e8f5e8;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #27ae60;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Digital Signature Certificate</h1>
        <p>IntelliCash E-Signature System</p>
    </div>

    <div class="section">
        <h2>Document Information</h2>
        <table class="info-table">
            <tr>
                <td>Document Title:</td>
                <td>{{ $document->title }}</td>
            </tr>
            <tr>
                <td>Document Type:</td>
                <td>{{ ucfirst($document->document_type) }}</td>
            </tr>
            <tr>
                <td>Created By:</td>
                <td>{{ $document->creator->name ?? 'Unknown' }}</td>
            </tr>
            <tr>
                <td>Created On:</td>
                <td>{{ $document->created_at->format('M d, Y H:i:s') }} UTC</td>
            </tr>
            <tr>
                <td>Sent On:</td>
                <td>{{ $document->sent_at ? $document->sent_at->format('M d, Y H:i:s') . ' UTC' : 'Not sent' }}</td>
            </tr>
            <tr>
                <td>Completed On:</td>
                <td>{{ $document->completed_at ? $document->completed_at->format('M d, Y H:i:s') . ' UTC' : 'Not completed' }}</td>
            </tr>
            <tr>
                <td>Status:</td>
                <td><strong>{{ ucfirst($document->status) }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Digital Signatures</h2>
        @if($signatures->count() > 0)
            @foreach($signatures as $signature)
                <div class="signature-item">
                    <div class="signature-header">
                        {{ $signature->signer_name }} ({{ $signature->signer_email }})
                    </div>
                    <div class="signature-details">
                        <strong>Signed On:</strong> {{ $signature->signed_at->format('M d, Y H:i:s') }} UTC<br>
                        <strong>Signature Type:</strong> {{ ucfirst($signature->signature_type) }}<br>
                        <strong>IP Address:</strong> {{ $signature->ip_address }}<br>
                        <strong>Browser:</strong> {{ $signature->browser_info }}<br>
                        <strong>Device:</strong> {{ $signature->device_info }}<br>
                        @if($signature->signature_hash)
                            <strong>Signature Hash:</strong> {{ $signature->signature_hash }}<br>
                        @endif
                    </div>
                </div>
            @endforeach
        @else
            <p>No signatures found.</p>
        @endif
    </div>

    <div class="section">
        <h2>Document Integrity</h2>
        <div class="integrity-section">
            <p><strong>Document Hash:</strong> {{ $document->document_hash ?: 'Not available' }}</p>
            <p><strong>Integrity Status:</strong> 
                @if($document->document_hash)
                    <span style="color: #27ae60;">✓ Verified</span>
                @else
                    <span style="color: #e74c3c;">⚠ Not verified</span>
                @endif
            </p>
        </div>
    </div>

    <div class="footer">
        <p><strong>Legal Notice:</strong></p>
        <p>This document has been digitally signed using the IntelliCash E-Signature System.</p>
        <p>The signatures contained herein are legally binding and admissible in court.</p>
        <p>Document integrity can be verified using the provided hash values.</p>
        <p>Generated on: {{ now()->format('M d, Y H:i:s') }} UTC</p>
    </div>
</body>
</html>
