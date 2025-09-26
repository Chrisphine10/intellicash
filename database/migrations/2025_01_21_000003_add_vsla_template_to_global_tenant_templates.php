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
        // Only proceed if email_templates table exists
        if (Schema::hasTable('email_templates')) {
            // Check if VSLA_MEETING_REMINDER template already exists as global tenant template
            $existingTemplate = DB::table('email_templates')
                ->where('slug', 'VSLA_MEETING_REMINDER')
                ->whereNull('tenant_id')
                ->where('template_type', 'tenant')
                ->first();

            if (!$existingTemplate) {
                // Create global VSLA template that tenants can customize
                DB::table('email_templates')->insert([
                    'name' => 'VSLA Meeting Reminder',
                    'slug' => 'VSLA_MEETING_REMINDER',
                    'subject' => 'VSLA Meeting Reminder - {{meetingDate}}',
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
        .highlight {
            color: #007bff;
            font-weight: bold;
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
                <p><strong>Date:</strong> <span class="highlight">{{meetingDate}}</span></p>
                <p><strong>Time:</strong> <span class="highlight">{{meetingTime}}</span></p>
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
                    'sms_body' => 'VSLA Meeting Reminder: {{meetingDate}} at {{meetingTime}}. Meeting days: {{meetingDays}}. Please attend on time. - {{vslaName}}',
                    'notification_body' => 'VSLA Meeting Reminder: {{meetingDate}} at {{meetingTime}}. Please attend on time.',
                    'shortcode' => '{{memberName}} {{meetingDate}} {{meetingTime}} {{meetingDays}} {{vslaName}}',
                    'email_status' => 1,
                    'sms_status' => 1,
                    'notification_status' => 1,
                    'template_mode' => 0,
                    'template_type' => 'tenant',
                    'tenant_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('email_templates')
            ->where('slug', 'VSLA_MEETING_REMINDER')
            ->whereNull('tenant_id')
            ->where('template_type', 'tenant')
            ->delete();
    }
};
