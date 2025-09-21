<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VSLA Meeting Reminder</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            width: 100% !important;
            min-width: 100% !important;
        }
        .container {
            width: 100% !important;
            max-width: 600px !important;
            background: #ffffff;
            margin: 0 auto !important;
            padding: 0 !important;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            table-layout: fixed;
        }
        .header {
            background: #007bff;
            color: #ffffff;
            text-align: center;
            padding: 15px 10px;
            font-size: 20px;
            font-weight: bold;
            border-radius: 8px 8px 0 0;
            word-wrap: break-word;
        }
        .content {
            padding: 15px 10px;
            color: #333;
            font-size: 14px;
            line-height: 1.5;
            word-wrap: break-word;
        }
        .meeting-details {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #007bff;
            word-wrap: break-word;
        }
        .footer {
            background: #f4f4f4;
            text-align: center;
            padding: 10px 5px;
            font-size: 12px;
            color: #666;
            border-radius: 0 0 8px 8px;
            word-wrap: break-word;
        }
        .highlight {
            color: #007bff;
            font-weight: bold;
        }
        p {
            margin: 8px 0;
            word-wrap: break-word;
        }
        strong {
            word-wrap: break-word;
        }
        /* Mobile responsiveness */
        @media only screen and (max-width: 600px) {
            .container {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                border-radius: 0 !important;
            }
            .header {
                font-size: 18px;
                padding: 12px 8px;
            }
            .content {
                padding: 12px 8px;
                font-size: 13px;
            }
            .meeting-details {
                padding: 8px;
                margin: 8px 0;
            }
            .footer {
                padding: 8px 5px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            VSLA Meeting Reminder
        </div>
        <div class="content">
            <p>Dear <strong>{{ $memberName }}</strong>,</p>
            
            <p>This is a reminder that your VSLA group meeting is scheduled for:</p>
            
            <div class="meeting-details">
                <p><strong>Date:</strong> <span class="highlight">{{ $meetingDate }}</span></p>
                <p><strong>Time:</strong> <span class="highlight">{{ $meetingTime }}</span></p>
                <p><strong>Meeting Days:</strong> {{ $meetingDays }}</p>
            </div>
            
            <p>Please ensure you attend the meeting on time. Your participation is important for the success of our {{ $vslaName }} group.</p>
            
            <p>If you have any questions or concerns, please contact your group leaders.</p>
            
            <p>Thank you for your commitment to our {{ $vslaName }} group.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ $vslaName }}. All Rights Reserved.
        </div>
    </div>
</body>
</html>
