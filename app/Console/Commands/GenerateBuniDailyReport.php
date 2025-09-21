<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Utilities\BuniActivityLogger;
use Carbon\Carbon;

class GenerateBuniDailyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'buni:daily-report {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily Buni activity report';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::yesterday();
        
        $this->info("Generating Buni daily report for: " . $date->format('Y-m-d'));
        
        // Get transaction statistics
        $deposits = DB::table('transactions')
            ->where('method', 'Buni')
            ->where('type', 'Deposit')
            ->whereDate('created_at', $date)
            ->get();
            
        $withdrawals = DB::table('withdraw_requests')
            ->whereHas('method', function($query) {
                $query->where('name', 'KCB Buni Mobile Money');
            })
            ->whereDate('created_at', $date)
            ->get();
            
        $summary = [
            'date' => $date->format('Y-m-d'),
            'total_deposits' => $deposits->count(),
            'total_deposit_amount' => $deposits->sum('amount'),
            'successful_deposits' => $deposits->where('status', 2)->count(),
            'failed_deposits' => $deposits->where('status', 3)->count(),
            'pending_deposits' => $deposits->where('status', 0)->count(),
            'total_withdrawals' => $withdrawals->count(),
            'total_withdrawal_amount' => $withdrawals->sum('amount'),
            'successful_withdrawals' => $withdrawals->where('status', 2)->count(),
            'failed_withdrawals' => $withdrawals->where('status', 3)->count(),
            'pending_withdrawals' => $withdrawals->where('status', 0)->count(),
        ];
        
        // Log the daily summary
        BuniActivityLogger::logDailySummary($summary);
        
        $this->info("Daily Report Summary:");
        $this->table(
            ['Metric', 'Count', 'Amount'],
            [
                ['Total Deposits', $summary['total_deposits'], number_format($summary['total_deposit_amount'], 2)],
                ['Successful Deposits', $summary['successful_deposits'], '-'],
                ['Failed Deposits', $summary['failed_deposits'], '-'],
                ['Pending Deposits', $summary['pending_deposits'], '-'],
                ['Total Withdrawals', $summary['total_withdrawals'], number_format($summary['total_withdrawal_amount'], 2)],
                ['Successful Withdrawals', $summary['successful_withdrawals'], '-'],
                ['Failed Withdrawals', $summary['failed_withdrawals'], '-'],
                ['Pending Withdrawals', $summary['pending_withdrawals'], '-'],
            ]
        );
        
        return 0;
    }
}
