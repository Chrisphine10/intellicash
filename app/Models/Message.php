<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Message extends Model {
    use MultiTenant;
    
    protected $fillable = [
        'uuid', 'sender_id', 'recipient_id', 'subject', 'body', 
        'status', 'is_replied', 'parent_id', 'tenant_id'
    ];

    protected $casts = [
        'is_replied' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
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
        return $this->hasMany(Message::class, 'parent_id')
            ->orderBy('created_at', 'asc');
    }

    public function attachments(){
        return $this->hasMany(MessageAttachment::class, 'message_id');
    }

    // Scopes
    public function scopeForUser(Builder $query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('sender_id', $userId)
              ->orWhere('recipient_id', $userId);
        });
    }

    public function scopeUnread(Builder $query)
    {
        return $query->where('status', 'unread');
    }

    public function scopeWithReplies(Builder $query)
    {
        return $query->where('is_replied', true);
    }

    // Helper methods
    public function isUnread()
    {
        return $this->status === 'unread';
    }

    public function markAsRead()
    {
        $this->update(['status' => 'read']);
    }

    public function getLastReplyForUser($userId)
    {
        return $this->replies()
            ->where('recipient_id', $userId)
            ->where('status', 'unread')
            ->orderBy('id', 'desc')
            ->first();
    }

    public function canAccess($userId)
    {
        return $this->sender_id == $userId || $this->recipient_id == $userId;
    }

    public function lastReplies() {
        $message = $this->replies()->where('recipient_id', auth()->id())
                                   ->where('status', 'unread')
                                   ->orderBy('id', 'desc')
                                   ->first();
        return $message;                          
    }
}
