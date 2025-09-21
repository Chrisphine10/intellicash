<?php

namespace App\Exports;

use App\Models\EmailSubscriber;
use Maatwebsite\Excel\Concerns\FromCollection;

class EmailSubscribersExport implements FromCollection
{
    public function collection()
    {
        return EmailSubscriber::select('created_at','email_address', 'ip_address')->get();
    }
}