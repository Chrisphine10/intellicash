<?php
namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Notifications\NewMessage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class MessageController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());
        
        // Apply rate limiting to prevent spam
        $this->middleware('throttle:10,1')->only(['send', 'sendReply']);
    }

    public function compose() {
        $alert_col = 'col-lg-8 offset-lg-2';
        return view('messages.compose', compact('alert_col'));
    }

    public function send(Request $request) {
        // Enhanced validation with better rules
        $validatedData = $request->validate([
            'recipient_id'  => 'required|exists:users,id',
            'subject'       => 'required|string|max:255|min:3',
            'body'          => 'required|string|min:1|max:10000',
            'attachments.*' => 'nullable|file|max:4096|mimes:png,jpg,jpeg,pdf,doc,docx,xlsx,csv',
        ]);

        // Check rate limiting
        $key = 'message_send:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->with('error', 'Too many messages sent. Please try again later.');
        }

        try {
            $message = DB::transaction(function() use ($validatedData, $request) {
                $message = Message::create([
                    'uuid'         => Str::uuid(),
                    'sender_id'    => auth()->id(),
                    'recipient_id' => $validatedData['recipient_id'],
                    'subject'      => $validatedData['subject'],
                    'body'         => $validatedData['body'],
                    'is_replied'   => false,
                ]);

                // Handle file attachments securely
                if ($request->hasFile('attachments')) {
                    $this->handleFileAttachments($request, $message);
                }

                return $message;
            });

            // Send notification asynchronously
            $this->sendNotification($message);
            
            RateLimiter::hit($key, 60); // 1 minute decay

            Log::info('Message sent successfully', [
                'message_id' => $message->id,
                'sender_id' => auth()->id(),
                'recipient_id' => $validatedData['recipient_id']
            ]);

            return redirect()->route('messages.sent')->with('success', 'Message sent successfully!');

        } catch (Exception $e) {
            Log::error('Message sending failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'recipient_id' => $validatedData['recipient_id'] ?? null
            ]);

            return back()->with('error', 'Failed to send message. Please try again.')
                ->withInput();
        }
    }

    public function inbox() {
        $alert_col = 'col-lg-8 offset-lg-2';
        $userId = auth()->id();
        
        $messages = Message::with(['sender', 'recipient', 'attachments'])
            ->whereNull('parent_id')
            ->where(function($query) use ($userId) {
                $query->where('recipient_id', $userId)
                      ->orWhere(function($subQuery) use ($userId) {
                          $subQuery->where('sender_id', $userId)
                                   ->where('is_replied', 1);
                      });
            })
            ->orderBy('id', 'desc')
            ->paginate(10);
            
        return view('messages.inbox', compact('messages', 'alert_col'));
    }

    public function sentItems() {
        $alert_col = 'col-lg-8 offset-lg-2';
        $messages = Message::with(['sender', 'recipient', 'attachments'])
            ->where('sender_id', auth()->id())
            ->whereNull('parent_id')
            ->orderBy('id', 'desc')
            ->paginate(10);
            
        return view('messages.sent', compact('messages', 'alert_col'));
    }

    public function show($tenant, $uuid) {
        $alert_col = 'col-lg-8 offset-lg-2';
        
        $message = Message::with(['sender', 'recipient', 'attachments', 'replies.sender'])
            ->where('uuid', $uuid)
            ->where('tenant_id', app('tenant')->id)
            ->where(function ($query) {
                $query->where('sender_id', auth()->id())
                    ->orWhere('recipient_id', auth()->id());
            })->firstOrFail();

        // Mark as read if user is recipient
        if ($message->recipient_id == auth()->id()) {
            $message->update(['status' => 'read']);
        }

        // Mark replies as read
        $message->replies()
            ->where('recipient_id', auth()->id())
            ->where('status', 'unread')
            ->update(['status' => 'read']);

        return view('messages.show', compact('message', 'alert_col'));
    }

    public function reply($tenant, $uuid) {
        $alert_col = 'col-lg-8 offset-lg-2';
        $message = Message::where('uuid', $uuid)
            ->where('tenant_id', app('tenant')->id)
            ->where(function ($query) {
                $query->where('sender_id', auth()->id())
                    ->orWhere('recipient_id', auth()->id());
            })->firstOrFail();
            
        return view('messages.reply', compact('message', 'alert_col'));
    }

    public function sendReply(Request $request, $tenant, $id) {
        $validatedData = $request->validate([
            'body'          => 'required|string|min:1|max:10000',
            'attachments.*' => 'nullable|file|max:4096|mimes:png,jpg,jpeg,pdf,doc,docx,xlsx,csv',
        ]);

        // Check rate limiting
        $key = 'message_reply:' . auth()->id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->with('error', 'Too many replies sent. Please try again later.');
        }

        try {
            $result = DB::transaction(function() use ($validatedData, $request, $id) {
                $originalMessage = Message::where('id', $id)
                    ->where('tenant_id', app('tenant')->id)
                    ->where(function ($query) {
                        $query->where('sender_id', auth()->id())
                            ->orWhere('recipient_id', auth()->id());
                    })->firstOrFail();

                $originalMessage->update(['is_replied' => true]);

                $message = Message::create([
                    'uuid'         => Str::uuid(),
                    'sender_id'    => auth()->id(),
                    'recipient_id' => $originalMessage->sender_id == auth()->id() ? $originalMessage->recipient_id : $originalMessage->sender_id,
                    'subject'      => 'Re: ' . $originalMessage->subject,
                    'body'         => $validatedData['body'],
                    'parent_id'    => $originalMessage->id,
                    'is_replied'   => false,
                ]);

                // Handle file attachments for replies
                if ($request->hasFile('attachments')) {
                    $this->handleFileAttachments($request, $message);
                }

                return $message;
            });

            // Send notification asynchronously
            $this->sendNotification($result);
            
            RateLimiter::hit($key, 60); // 1 minute decay

            Log::info('Reply sent successfully', [
                'reply_id' => $result->id,
                'original_message_id' => $id,
                'sender_id' => auth()->id()
            ]);

            return redirect()->route('messages.inbox')->with('success', 'Reply sent successfully!');

        } catch (Exception $e) {
            Log::error('Reply sending failed', [
                'user_id' => auth()->id(),
                'original_message_id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to send reply. Please try again.')
                ->withInput();
        }
    }

    public function download_attachment($id) {
        $attachment = MessageAttachment::where('id', $id)
            ->whereHas('message', function($query) {
                $query->where('tenant_id', app('tenant')->id)
                      ->where(function($subQuery) {
                          $subQuery->where('sender_id', auth()->id())
                                   ->orWhere('recipient_id', auth()->id());
                      });
            })->firstOrFail();

        Log::info('Attachment downloaded', [
            'attachment_id' => $attachment->id,
            'message_id' => $attachment->message_id,
            'user_id' => auth()->id()
        ]);

        return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }

    /**
     * Handle file attachments securely
     */
    private function handleFileAttachments(Request $request, Message $message)
    {
        foreach ($request->file('attachments') as $file) {
            // Validate file content by MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file->getPathname());
            finfo_close($finfo);
            
            $allowedMimes = [
                'image/jpeg', 'image/png', 'image/gif',
                'application/pdf', 'text/plain',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv'
            ];
            
            if (!in_array($mimeType, $allowedMimes)) {
                throw new \Exception('Invalid file type: ' . $mimeType);
            }

            // Generate secure filename to prevent directory traversal
            $secureFileName = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('attachments', $secureFileName, 'public');
            
            MessageAttachment::create([
                'message_id' => $message->id,
                'file_path'  => $filePath,
                'file_name'  => $file->getClientOriginalName(),
                'mime_type'  => $mimeType,
                'file_size'  => $file->getSize(),
            ]);
        }
    }

    /**
     * Send notification safely
     */
    private function sendNotification(Message $message)
    {
        try {
            if ($message->recipient->user_type == 'customer') {
                $message->recipient->member->notify(new NewMessage($message));
            } else {
                $message->recipient->notify(new NewMessage($message));
            }
        } catch (Exception $e) {
            Log::error('Notification failed', [
                'message_id' => $message->id,
                'recipient_id' => $message->recipient_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
