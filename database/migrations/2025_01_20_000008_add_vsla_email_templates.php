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
            $templates = [
                [
                    "name" => "VSLA Meeting Reminder",
                    "slug" => "VSLA_MEETING_REMINDER",
                    "subject" => "VSLA Meeting Reminder - {{meetingDate}}",
                    "email_body" => "<div style='font-family: Arial, sans-serif; font-size: 14px;'> <h2 style='color: #333333;'>VSLA Meeting Reminder</h2> <p>Dear {{memberName}},</p> <p>This is a reminder that your VSLA group meeting is scheduled for:</p> <p><strong>Date:</strong> {{meetingDate}}<br> <strong>Time:</strong> {{meetingTime}}<br> <strong>Meeting Days:</strong> {{meetingDays}}</p> <p>Please ensure you attend the meeting on time. Your participation is important for the success of our VSLA group.</p> <p>If you have any questions or concerns, please contact your group leaders.</p> <p>Thank you for your commitment to our VSLA group.</p> </div>",
                    "sms_body" => "VSLA Meeting Reminder: {{meetingDate}} at {{meetingTime}}. Please attend on time. - {{vslaName}}",
                    "notification_body" => "VSLA Meeting Reminder: {{meetingDate}} at {{meetingTime}}. Please attend on time.",
                    "shortcode" => "{{memberName}} {{meetingDate}} {{meetingTime}} {{meetingDays}} {{vslaName}}",
                    "email_status" => 1,
                    "sms_status" => 1,
                    "notification_status" => 1,
                    "template_mode" => 0,
                    "template_type" => 'tenant',
                ],
                [
                    "name" => "VSLA Role Assignment",
                    "slug" => "VSLA_ROLE_ASSIGNMENT",
                    "subject" => "VSLA Role Assignment - {{roleName}}",
                    "email_body" => "<div style='font-family: Arial, sans-serif; font-size: 14px;'> <h2 style='color: #333333;'>VSLA Role Assignment</h2> <p>Dear {{memberName}},</p> <p>Congratulations! You have been assigned the role of <strong>{{roleName}}</strong> in your VSLA group.</p> <p><strong>Assignment Details:</strong></p> <p><strong>Role:</strong> {{roleName}}<br> <strong>Assigned by:</strong> {{assignedBy}}<br> <strong>Date:</strong> {{assignedDate}}<br> @if({{notes}}) <strong>Notes:</strong> {{notes}} @endif</p> <p>As a {{roleName}}, you have important responsibilities in our VSLA group. Please fulfill your duties with dedication and commitment.</p> <p>If you have any questions about your role or responsibilities, please don't hesitate to contact the group leadership.</p> <p>Thank you for your continued participation in our VSLA group.</p> </div>",
                    "sms_body" => "Congratulations! You have been assigned as {{roleName}} in your VSLA group. Please fulfill your responsibilities. - {{vslaName}}",
                    "notification_body" => "You have been assigned the role of {{roleName}} in your VSLA group. Please fulfill your responsibilities.",
                    "shortcode" => "{{memberName}} {{roleName}} {{assignedBy}} {{assignedDate}} {{notes}} {{vslaName}}",
                    "email_status" => 1,
                    "sms_status" => 1,
                    "notification_status" => 1,
                    "template_mode" => 0,
                    "template_type" => 'tenant',
                ],
            ];

            foreach ($templates as $template) {
                $existingTemplate = DB::table('email_templates')
                    ->where('slug', $template['slug'])
                    ->first();

                if (!$existingTemplate) {
                    DB::table('email_templates')->insert($template);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('email_templates')
            ->whereIn('slug', ['VSLA_MEETING_REMINDER', 'VSLA_ROLE_ASSIGNMENT'])
            ->delete();
    }
};
