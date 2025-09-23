<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Guarantor Invitation</title>
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
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f8fff8;
            padding: 30px;
            border: 1px solid #d4edda;
            border-top: none;
        }
        .loan-details {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        .buttons {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            margin: 0 10px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .btn-accept {
            background: #28a745;
            color: white;
        }
        .btn-decline {
            background: #dc3545;
            color: white;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-radius: 0 0 10px 10px;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Guarantor Invitation</h1>
        <p>You have been invited to be a guarantor for a loan application</p>
    </div>

    <div class="content">
        <p>Dear {{ $guarantorRequest->guarantor_name }},</p>

        <p><strong>{{ $guarantorRequest->borrower->first_name }} {{ $guarantorRequest->borrower->last_name }}</strong> has requested you to be their guarantor for a loan application.</p>

        @if($guarantorRequest->guarantor_message)
        <div style="background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <strong>Personal Message:</strong><br>
            {{ $guarantorRequest->guarantor_message }}
        </div>
        @endif

        <div class="loan-details">
            <h3>Loan Details</h3>
            <p><strong>Borrower:</strong> {{ $guarantorRequest->borrower->first_name }} {{ $guarantorRequest->borrower->last_name }}</p>
            <p><strong>Loan Amount:</strong> {{ number_format($guarantorRequest->loan->applied_amount, 2) }} {{ $guarantorRequest->loan->currency->name }}</p>
            <p><strong>Purpose:</strong> {{ $guarantorRequest->loan->comprehensive_data ? json_decode($guarantorRequest->loan->comprehensive_data, true)['loan_purpose'] ?? 'Not specified' : 'Not specified' }}</p>
            <p><strong>Application Date:</strong> {{ $guarantorRequest->loan->created_at->format('M d, Y') }}</p>
        </div>

        <div class="warning">
            <strong>Important:</strong> As a guarantor, you will be responsible for repaying this loan if the borrower defaults. Please consider this carefully before accepting.
        </div>

        <div class="buttons">
            <a href="{{ route('guarantor.accept', ['token' => $guarantorRequest->token]) }}" class="btn btn-accept">Accept Guarantee</a>
            <a href="{{ route('guarantor.decline', ['token' => $guarantorRequest->token]) }}" class="btn btn-decline">Decline</a>
        </div>

        <p><strong>This invitation expires on:</strong> {{ $guarantorRequest->expires_at->format('M d, Y \a\t g:i A') }}</p>

        <p>If you did not expect this invitation or have any questions, please contact our support team.</p>

        <p>Thank you for your consideration.</p>
    </div>

    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} {{ app('tenant')->name ?? 'IntelliCash' }}. All rights reserved.</p>
    </div>
</body>
</html>
