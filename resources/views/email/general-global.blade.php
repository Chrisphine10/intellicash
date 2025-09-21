<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $mail->subject ?? 'Notification' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 600px;
            background: #ffffff;
            margin: 20px auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: #007bff;
            color: #ffffff;
            text-align: center;
            padding: 15px;
            font-size: 24px;
            font-weight: bold;
            border-radius: 8px 8px 0 0;
        }
        .content {
            padding: 20px;
            color: #333;
            font-size: 16px;
            line-height: 1.6;
        }
        .footer {
            background: #f4f4f4;
            text-align: center;
            padding: 10px;
            font-size: 14px;
            color: #666;
            border-radius: 0 0 8px 8px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            color: #ffffff;
            background: #007bff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {{ $mail->subject }}
        </div>
        <div class="content">
            {!! xss_clean($mail->message) !!}
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ get_option('company_name') }}. All Rights Reserved.
        </div>
    </div>
</body>
</html>
