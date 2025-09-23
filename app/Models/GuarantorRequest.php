<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class GuarantorRequest extends Model
{
    use HasFactory, MultiTenant;

    protected $fillable = [
        'tenant_id',
        'loan_id',
        'borrower_id',
        'guarantor_email',
        'guarantor_name',
        'guarantor_message',
        'token',
        'status',
        'expires_at',
        'responded_at',
        'response_message',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function borrower()
    {
        return $this->belongsTo(Member::class, 'borrower_id');
    }

    public function guarantor()
    {
        return $this->belongsTo(Member::class, 'guarantor_email', 'email');
    }

    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    public function isPending()
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending')
                    ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public static function generateToken()
    {
        return bin2hex(random_bytes(32));
    }

    public static function createRequest($loanId, $borrowerId, $guarantorEmail, $guarantorName, $message = null)
    {
        return self::create([
            'tenant_id' => app('tenant')->id,
            'loan_id' => $loanId,
            'borrower_id' => $borrowerId,
            'guarantor_email' => $guarantorEmail,
            'guarantor_name' => $guarantorName,
            'guarantor_message' => $message,
            'token' => self::generateToken(),
            'expires_at' => now()->addDays(7), // 7 days to respond
        ]);
    }
}
