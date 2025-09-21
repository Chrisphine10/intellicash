<?php
namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Notifications\NewMessage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());
    }

    public function compose() {
        $alert_col = 'col-lg-8 offset-lg-2';
        return view('messages.compose', compact('alert_col'));
    }

    public function send(Request $request) {
        $validatedData = $request->validate([
            'recipient_id'  => 'required|exists:users,id',
            'subject'       => 'required|string|max:255',
            'body'          => 'required|string',
            'attachments.*' => 'nullable|mimes:png,jpg,jpeg,pdf,doc,docx,xlsx,csv|max:4096', // Validate files (optional)
        ]);

        // Create the message
        $message = Message::create([
            'uuid'         => Str::uuid(),
            'sender_id'    => auth()->id(),
            'recipient_id' => $validatedData['recipient_id'],
            'subject'      => $validatedData['subject'],
            'body'         => $validatedData['body'],
            'is_replied'   => false,
        ]);

        // Handle file attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filePath = $file->store('attachments', 'public');
                MessageAttachment::create([
                    'message_id' => $message->id,
                    'file_path'  => $filePath,
                    'file_name'  => $file->getClientOriginalName(),
                ]);
            }
        }

        try {
            if ($message->recipient->user_type == 'customer') {
                $message->recipient->member->notify(new NewMessage($message));
            } else {
                $message->recipient->notify(new NewMessage($message));
            }
        } catch (Exception $e) {}

        return redirect()->route('messages.sent')->with('success', 'Message sent successfully!');
    }

    public function inbox() {
        $alert_col = 'col-lg-8 offset-lg-2';
        $userId    = auth()->id();
        $messages  = Message::whereNull('parent_id')
            ->whereRaw("recipient_id = '$userId' OR (sender_id = '$userId' AND is_replied= 1)")
            ->orderBy('id', 'desc')
            ->paginate(10);
        return view('messages.inbox', compact('messages', 'alert_col'));
    }

    public function sentItems() {
        $alert_col = 'col-lg-8 offset-lg-2';
        $messages  = Message::where('sender_id', auth()->id())
            ->whereNull('parent_id')
            ->paginate(10);
        return view('messages.sent', compact('messages', 'alert_col'));
    }

    public function show($tenant, $uuid) {
        $alert_col = 'col-lg-8 offset-lg-2';
        $message   = Message::where('uuid', $uuid)
            ->where(function ($query) {
                $query->where('sender_id', auth()->id())
                    ->orWhere('recipient_id', auth()->id());
            })->firstOrFail();

        if ($message->recipient_id == auth()->id()) {
            $message->status = 'read';
            $message->save();
        }

        $message->replies()->where('recipient_id', auth()->id())
            ->where('status', 'unread')
            ->update(['status' => 'read']);

        return view('messages.show', compact('message', 'alert_col'));
    }

    public function reply($tenant, $uuid) {
        $alert_col = 'col-lg-8 offset-lg-2';
        $message   = Message::where('uuid', $uuid)->first();
        return view('messages.reply', compact('message', 'alert_col'));
    }

    public function sendReply(Request $request, $tenant, $id) {
        $validatedData = $request->validate([
            'body'          => 'required|string',
            'attachments.*' => 'nullable|file|max:4096', // Validate files (optional)
        ]);

        $originalMessage             = Message::findOrFail($id);
        $originalMessage->is_replied = true;
        $originalMessage->save();

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
            foreach ($request->file('attachments') as $file) {
                $filePath = $file->store('attachments', 'public');
                MessageAttachment::create([
                    'message_id' => $message->id,
                    'file_path'  => $filePath,
                    'file_name'  => $file->getClientOriginalName(),
                ]);
            }
        }

        try {
            if ($message->recipient->user_type == 'customer') {
                $message->recipient->member->notify(new NewMessage($message));
            } else {
                $message->recipient->notify(new NewMessage($message));
            }
        } catch (Exception $e) {}

        return redirect()->route('messages.inbox')->with('success', 'Reply sent successfully!');
    }

    public function download_attachment($id) {
        $attachment = MessageAttachment::find($id);
        return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }
}
