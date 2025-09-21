<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the VSLA Meeting Reminder email template with proper HTML structure
        DB::table('email_templates')
            ->where('slug', 'VSLA_MEETING_REMINDER')
            ->update([
                'email_body' => '<!DOCTYPE html>
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
        .meeting-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #007bff;
        }
        .footer {
            background: #f4f4f4;
            text-align: center;
            padding: 10px;
            font-size: 14px;
            color: #666;
            border-radius: 0 0 8px 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            VSLA Meeting Reminder
        </div>
        <div class="content">
            <p>Dear <strong>{{memberName}}</strong>,</p>
            
            <p>This is a reminder that your VSLA group meeting is scheduled for:</p>
            
            <div class="meeting-details">
                <p><strong>Date:</strong> {{meetingDate}}</p>
                <p><strong>Time:</strong> {{meetingTime}}</p>
                <p><strong>Meeting Days:</strong> {{meetingDays}}</p>
            </div>
            
            <p>Please ensure you attend the meeting on time. Your participation is important for the success of our {{vslaName}} group.</p>
            
            <p>If you have any questions or concerns, please contact your group leaders.</p>
            
            <p>Thank you for your commitment to our {{vslaName}} group.</p>
        </div>
        <div class="footer">
            &copy; {{ date("Y") }} {{vslaName}}. All Rights Reserved.
        </div>
    </div>
</body>
</html>',
                'subject' => 'VSLA Meeting Reminder - {{meetingDate}}'
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to the original template
        DB::table('email_templates')
            ->where('slug', 'VSLA_MEETING_REMINDER')
            ->update([
                'email_body' => "<div style='font-family: Arial, sans-serif; font-size: 14px;'> <h2 style='color: #333333;'>VSLA Meeting Reminder</h2> <p>Dear {{memberName}},</p> <p>This is a reminder that your VSLA group meeting is scheduled for:</p> <p><strong>Date:</strong> {{meetingDate}}<br> <strong>Time:</strong> {{meetingTime}}<br> <strong>Meeting Days:</strong> {{meetingDays}}</p> <p>Please ensure you attend the meeting on time. Your participation is important for the success of our VSLA group.</p> <p>If you have any questions or concerns, please contact your group leaders.</p> <p>Thank you for your commitment to our VSLA group.</p> </div>",
                'subject' => 'VSLA Meeting Reminder - {{meetingDate}}'
            ]);
    }
};
