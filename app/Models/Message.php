<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class Message extends Model {
    use MultiTenant;
    
    protected $fillable = [
        'uuid', 'sender_id', 'recipient_id', 'subject', 'body', 'status', 'is_replied', 'parent_id',
    ];

    public function sender() {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient() {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function parentMessage() {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    public function replies() {
        return $this->hasMany(Message::class, 'parent_id');
    }

    public function lastReplies() {
        $message = $this->replies()->where('recipient_id', auth()->id())
                                   ->where('status', 'unread')
                                   ->orderBy('id', 'desc')
                                   ->first();
        return $message;                          
    }

    public function attachments(){
        return $this->hasMany(MessageAttachment::class, 'message_id');
    }
}
