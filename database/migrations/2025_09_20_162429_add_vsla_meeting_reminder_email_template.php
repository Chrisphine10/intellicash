<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\EmailTemplate;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create VSLA Meeting Reminder email template for all tenants
        $tenants = \App\Models\Tenant::all();
        
        foreach ($tenants as $tenant) {
            // Check if template already exists for this tenant
            $existingTemplate = EmailTemplate::where('slug', 'VSLA_MEETING_REMINDER')
                ->where('tenant_id', $tenant->id)
                ->first();
                
            if (!$existingTemplate) {
                EmailTemplate::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'VSLA Meeting Reminder',
                    'slug' => 'VSLA_MEETING_REMINDER',
                    'subject' => 'VSLA Meeting Reminder - {{meetingDate}}',
                    'email_body' => '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333;">
                        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                                <h1 style="margin: 0; font-size: 24px;">VSLA Meeting Reminder</h1>
                                <p style="margin: 10px 0 0 0; opacity: 0.9;">{{vslaName}}</p>
                            </div>
                            
                            <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
                                <p style="margin: 0 0 20px 0; font-size: 16px;">Dear <strong>{{memberName}}</strong>,</p>
                                
                                <p style="margin: 0 0 20px 0;">This is a friendly reminder about your upcoming VSLA meeting:</p>
                                
                                <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea; margin: 20px 0;">
                                    <h3 style="margin: 0 0 15px 0; color: #667eea;">Meeting Details</h3>
                                    <p style="margin: 5px 0;"><strong>Date:</strong> {{meetingDate}}</p>
                                    <p style="margin: 5px 0;"><strong>Time:</strong> {{meetingTime}}</p>
                                    <p style="margin: 5px 0;"><strong>Meeting Days:</strong> {{meetingDays}}</p>
                                </div>
                                
                                <p style="margin: 20px 0;">Please ensure you:</p>
                                <ul style="margin: 10px 0; padding-left: 20px;">
                                    <li>Arrive on time for the meeting</li>
                                    <li>Bring your contribution amount if applicable</li>
                                    <li>Prepare any reports or updates you need to share</li>
                                    <li>Bring your VSLA passbook or records</li>
                                </ul>
                                
                                <p style="margin: 20px 0;">If you cannot attend, please inform the group secretary or chairperson in advance.</p>
                                
                                <div style="text-align: center; margin: 30px 0;">
                                    <p style="margin: 0; color: #666; font-size: 12px;">
                                        This is an automated reminder from your VSLA management system.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>',
                    'sms_body' => 'VSLA Meeting Reminder: {{meetingDate}} at {{meetingTime}}. Meeting days: {{meetingDays}}. Please attend on time. - {{vslaName}}',
                    'notification_body' => 'VSLA Meeting Reminder: {{meetingDate}} at {{meetingTime}}. Please attend on time.',
                    'shortcode' => '{{memberName}} {{meetingDate}} {{meetingTime}} {{meetingDays}} {{vslaName}}',
                    'email_status' => 1,
                    'sms_status' => 1,
                    'notification_status' => 1,
                    'template_mode' => 0,
                    'template_type' => 'tenant',
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove VSLA Meeting Reminder templates
        EmailTemplate::where('slug', 'VSLA_MEETING_REMINDER')->delete();
    }
};