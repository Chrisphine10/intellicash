<?php
namespace App\Listeners;

use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Registered;

class SendWelcomeNotification {
    /**
     * Create the event listener.
     */
    public function __construct() {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void {
        $event->user->notify(new WelcomeNotification($event->user));
    }
}
