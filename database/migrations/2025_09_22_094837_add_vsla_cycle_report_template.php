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
        // Add VSLA Cycle Report template
        EmailTemplate::create([
            'name' => 'VSLA Cycle Report',
            'slug' => 'VSLA_CYCLE_REPORT',
            'subject' => 'VSLA Cycle Report - {cycle_name}',
            'email_body' => '<p>Dear {member_name},</p>
<p>Your VSLA cycle report for <strong>{cycle_name}</strong> is now available.</p>
<p><strong>Cycle Status:</strong> {cycle_status}</p>
<p><strong>Your Shares:</strong> {member_shares}</p>
<p><strong>Expected Return:</strong> {expected_return} {currency}</p>
<p>Please log in to your account to view the complete report with detailed breakdown of your contributions, loan activities, and account balances.</p>
<p>Best regards,<br>{company_name}</p>',
            'sms_body' => 'VSLA Cycle Report - {cycle_name}\nStatus: {cycle_status}\nYour Share: {expected_return} {currency}\nShares: {member_shares}\nCheck email for details.',
            'notification_body' => 'Your VSLA cycle report for {cycle_name} is ready. Expected return: {expected_return} {currency}',
            'shortcode' => '{member_name}, {cycle_name}, {cycle_status}, {member_shares}, {expected_return}, {currency}, {company_name}',
            'email_status' => 1,
            'sms_status' => 1,
            'notification_status' => 1,
            'template_mode' => 0,
            'template_type' => 'tenant',
            'tenant_id' => null,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        EmailTemplate::where('slug', 'VSLA_CYCLE_REPORT')->delete();
    }
};