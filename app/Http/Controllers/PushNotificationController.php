<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MemberPushNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PushNotificationController extends Controller
{
    protected $pushService;

    public function __construct(MemberPushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * Register for push notifications (members only)
     */
    public function register(Request $request)
    {
        // Only allow members to register for push notifications
        if (!Auth::check() || Auth::user()->user_type !== 'customer') {
            return response()->json([
                'success' => false,
                'message' => 'Only members can register for push notifications'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|url',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid subscription data',
                'errors' => $validator->errors()
            ], 400);
        }

        $success = $this->pushService->registerMember(
            Auth::id(),
            $request->all()
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Successfully registered for notifications' : 'Failed to register'
        ]);
    }

    /**
     * Unregister from push notifications
     */
    public function unregister(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $success = $this->pushService->unregisterMember(
            Auth::id(),
            $request->input('endpoint')
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Successfully unregistered from notifications' : 'Failed to unregister'
        ]);
    }

    /**
     * Test push notification (admin only)
     */
    public function test(Request $request)
    {
        if (!Auth::check() || !in_array(Auth::user()->user_type, ['admin', 'user', 'superadmin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Admin access required'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'member_id' => 'required|exists:members,id',
            'title' => 'required|string|max:100',
            'body' => 'required|string|max:200'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid test data',
                'errors' => $validator->errors()
            ], 400);
        }

        $success = $this->pushService->sendGeneralNotification(
            $request->member_id,
            $request->title,
            $request->body
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Test notification sent' : 'Failed to send notification'
        ]);
    }

    /**
     * Get push notification status
     */
    public function status()
    {
        if (!Auth::check()) {
            return response()->json([
                'enabled' => false,
                'registered' => false
            ]);
        }

        $subscription = \App\Models\PushSubscription::where('user_id', Auth::id())
            ->where('active', true)
            ->first();

        return response()->json([
            'enabled' => get_tenant_option('pwa_enabled', 1),
            'registered' => $subscription ? true : false,
            'supports_push' => $this->supportsPushNotifications()
        ]);
    }

    /**
     * Check if browser supports push notifications
     */
    private function supportsPushNotifications()
    {
        return request()->header('User-Agent') && 
               (strpos(request()->header('User-Agent'), 'Chrome') !== false ||
                strpos(request()->header('User-Agent'), 'Firefox') !== false ||
                strpos(request()->header('User-Agent'), 'Safari') !== false);
    }
}
