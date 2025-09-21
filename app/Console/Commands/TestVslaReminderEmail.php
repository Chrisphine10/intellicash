<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VslaSetting;
use App\Models\Member;
use App\Notifications\VslaMeetingReminder;
use Carbon\Carbon;

class TestVslaReminderEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:vsla-reminder-email {--tenant-id=1 : Tenant ID to test with} {--email= : Specific email to send to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send test VSLA reminder emails to users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->option('tenant-id');
        $specificEmail = $this->option('email');
        
        $this->info("Testing VSLA reminder emails for tenant ID: {$tenantId}");
        
        // Get VSLA settings
        $vslaSettings = VslaSetting::where('tenant_id', $tenantId)->first();
        
        if (!$vslaSettings) {
            $this->error("No VSLA settings found for tenant ID: {$tenantId}");
            return 1;
        }
        
        $this->info("VSLA Settings found:");
        $this->info("- Meeting Time: " . $vslaSettings->getFormattedMeetingTime());
        $this->info("- Meeting Days: " . $vslaSettings->getMeetingDaysString());
        $this->info("- Tenant: " . $vslaSettings->tenant->name);
        
        // Get next meeting date
        $nextMeetingDate = $vslaSettings->getNextMeetingDate();
        if (!$nextMeetingDate) {
            $this->error("Could not determine next meeting date");
            return 1;
        }
        
        $this->info("- Next Meeting Date: " . $nextMeetingDate->format('l, F j, Y'));
        
        // Get members
        $membersQuery = Member::where('tenant_id', $tenantId)->with('user');
        
        if ($specificEmail) {
            $membersQuery->whereHas('user', function($query) use ($specificEmail) {
                $query->where('email', $specificEmail);
            });
        }
        
        $members = $membersQuery->get();
        
        if ($members->isEmpty()) {
            $this->error("No members found for tenant ID: {$tenantId}");
            return 1;
        }
        
        $this->info("Found " . $members->count() . " member(s) to send emails to:");
        
        foreach ($members as $member) {
            $this->info("- " . $member->user->name . " (" . $member->user->email . ")");
        }
        
        if ($this->confirm('Do you want to send test emails to these members?')) {
            $this->info("Sending test emails...");
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($members as $member) {
                try {
                    $member->user->notify(new VslaMeetingReminder($vslaSettings, $nextMeetingDate));
                    $this->info("✓ Email sent to: " . $member->user->name . " (" . $member->user->email . ")");
                    $successCount++;
                } catch (\Exception $e) {
                    $this->error("✗ Failed to send email to: " . $member->user->name . " - " . $e->getMessage());
                    $errorCount++;
                }
            }
            
            $this->info("\nEmail sending completed:");
            $this->info("✓ Successfully sent: {$successCount}");
            $this->info("✗ Failed: {$errorCount}");
            
            if ($successCount > 0) {
                $this->info("\nPlease check the email inboxes to verify the email formatting and content.");
            }
        } else {
            $this->info("Test email sending cancelled.");
        }
        
        return 0;
    }
}
