<?php

namespace App\Cronjobs;

use App\Models\VslaSetting;
use App\Models\Member;
use App\Notifications\VslaMeetingReminder;
use Carbon\Carbon;
use Exception;

class VslaMeetingReminderTask
{
    public function __invoke()
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        // Get all VSLA settings with meeting days configured
        $vslaSettings = VslaSetting::with('tenant')
            ->whereNotNull('meeting_days')
            ->where('meeting_days', '!=', '[]')
            ->get();

        foreach ($vslaSettings as $settings) {
            try {
                $this->processVslaReminders($settings);
            } catch (Exception $e) {
                \Log::error('VSLA Meeting Reminder Error for tenant ' . $settings->tenant_id . ': ' . $e->getMessage());
            }
        }
    }

    private function processVslaReminders($settings)
    {
        $now = Carbon::now();
        $reminderTime = $now->copy()->addHours(2); // 2 hours from now
        
        // Get meeting days as array
        $meetingDays = $settings->getMeetingDaysArray();
        
        if (empty($meetingDays)) {
            return;
        }

        $dayMap = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 0
        ];

        // Check if there's a meeting today or tomorrow within 2 hours
        foreach ($meetingDays as $day) {
            $dayNumber = $dayMap[$day] ?? null;
            if ($dayNumber === null) {
                continue;
            }

            // Check today's meeting
            $todayMeeting = $now->copy()->setTimeFromTimeString($settings->getFormattedMeetingTime());
            if ($todayMeeting->isToday() && $todayMeeting->dayOfWeek == $dayNumber) {
                if ($this->shouldSendReminder($todayMeeting, $reminderTime)) {
                    $this->sendReminders($settings, $todayMeeting);
                }
            }

            // Check tomorrow's meeting (for early morning reminders)
            $tomorrowMeeting = $now->copy()->addDay()->setTimeFromTimeString($settings->getFormattedMeetingTime());
            if ($tomorrowMeeting->isTomorrow() && $tomorrowMeeting->dayOfWeek == $dayNumber) {
                if ($this->shouldSendReminder($tomorrowMeeting, $reminderTime)) {
                    $this->sendReminders($settings, $tomorrowMeeting);
                }
            }
        }
    }

    private function shouldSendReminder($meetingTime, $reminderTime)
    {
        // Send reminder if meeting is between 1.5 and 2.5 hours in the future
        $now = now();
        $timeDiff = $now->diffInMinutes($meetingTime, false);
        
        // Send if meeting is between 90 and 150 minutes in the future
        return $timeDiff >= 90 && $timeDiff <= 150;
    }

    private function sendReminders($settings, $meetingDate)
    {
        // Get all members for this tenant
        $members = Member::where('tenant_id', $settings->tenant_id)
            ->where('status', 1) // Active members only
            ->get();

        foreach ($members as $member) {
            try {
                // Check if we already sent a reminder for this meeting
                $reminderKey = 'vsla_reminder_' . $settings->tenant_id . '_' . $meetingDate->format('Y-m-d');
                
                if (!$member->$reminderKey) {
                    $member->notify(new VslaMeetingReminder($settings, $meetingDate));
                    
                    // Mark reminder as sent (using a custom field or cache)
                    $member->$reminderKey = now();
                    $member->save();
                }
            } catch (Exception $e) {
                \Log::error('Failed to send VSLA reminder to member ' . $member->id . ': ' . $e->getMessage());
            }
        }
    }
}
