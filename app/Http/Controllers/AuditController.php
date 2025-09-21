<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DataTables;

class AuditController extends Controller
{
    /**
     * Display audit trails for admin users
     */
    public function index(Request $request)
    {
        $assets = ['datatable'];
        
        // Get filter options - only for current tenant
        $eventTypes = AuditTrail::where('tenant_id', request()->tenant->id)->distinct()->pluck('event_type')->sort();
        $auditableTypes = AuditTrail::where('tenant_id', request()->tenant->id)->distinct()->pluck('auditable_type')->sort();
        $userTypes = AuditTrail::where('tenant_id', request()->tenant->id)->distinct()->pluck('user_type')->sort();
        
        return view('backend.admin.audit.index', compact('assets', 'eventTypes', 'auditableTypes', 'userTypes'));
    }

    /**
     * Get audit trails data for DataTable
     */
    public function getTableData(Request $request)
    {
        $query = AuditTrail::with(['user', 'auditable'])
            ->where('tenant_id', request()->tenant->id)
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->auditable_type);
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return DataTables::eloquent($query)
            ->editColumn('user_name', function ($audit) {
                if ($audit->user) {
                    return $audit->user->name ?? $audit->user->first_name . ' ' . $audit->user->last_name;
                }
                return 'System';
            })
            ->editColumn('auditable_name', function ($audit) {
                if ($audit->auditable) {
                    $modelName = class_basename($audit->auditable_type);
                    switch ($modelName) {
                        case 'BankAccount':
                            return "Bank Account: {$audit->auditable->account_name} ({$audit->auditable->account_number})";
                        case 'Transaction':
                            return "Transaction #{$audit->auditable_id} - {$audit->auditable->type}";
                        case 'BankTransaction':
                            return "Bank Transaction #{$audit->auditable_id} - {$audit->auditable->type}";
                        default:
                            return "{$modelName} #{$audit->auditable_id}";
                    }
                }
                return "{$audit->auditable_type} #{$audit->auditable_id}";
            })
            ->editColumn('event_type', function ($audit) {
                $badgeClass = match($audit->event_type) {
                    'created' => 'success',
                    'updated' => 'warning',
                    'deleted' => 'danger',
                    'viewed' => 'info',
                    'balance_changed' => 'primary',
                    'transaction_modified' => 'warning',
                    default => 'secondary'
                };
                return '<span class="badge badge-' . $badgeClass . '">' . ucfirst(str_replace('_', ' ', $audit->event_type)) . '</span>';
            })
            ->editColumn('created_at', function ($audit) {
                return $audit->created_at->format('Y-m-d H:i:s');
            })
            ->addColumn('changes', function ($audit) {
                try {
                    $changesSummary = $audit->changes_summary;
                    if ($changesSummary && is_array($changesSummary) && count($changesSummary) > 0) {
                        $changes = collect($changesSummary)->take(3);
                        $html = '<ul class="list-unstyled mb-0">';
                        foreach ($changes as $field => $change) {
                            if (is_array($change) && isset($change['old']) && isset($change['new'])) {
                                $html .= '<li><small>' . ucfirst(str_replace('_', ' ', $field)) . ': ' . 
                                        $change['old'] . ' → ' . $change['new'] . '</small></li>';
                            }
                        }
                        if (count($changesSummary) > 3) {
                            $html .= '<li><small>... and ' . (count($changesSummary) - 3) . ' more</small></li>';
                        }
                        $html .= '</ul>';
                        return $html;
                    }
                } catch (\Exception $e) {
                    // Log error and return safe fallback
                    \Log::warning('Error processing audit changes summary', [
                        'audit_id' => $audit->id,
                        'error' => $e->getMessage()
                    ]);
                }
                return '-';
            })
            ->addColumn('action', function ($audit) {
                return '<a href="' . route('audit.show', $audit->id) . '" class="btn btn-sm btn-info" data-title="' . _lang('Audit Details') . '">
                    <i class="fas fa-eye"></i> ' . _lang('View') . '
                </a>';
            })
            ->rawColumns(['event_type', 'changes', 'action'])
            ->make(true);
    }

    /**
     * Show audit trail details
     */
    public function show($tenant, $audit)
    {
        try {
            $audit = AuditTrail::with(['user', 'auditable'])
                ->where('tenant_id', request()->tenant->id ?? 1)
                ->findOrFail($audit);
            
            // Ensure all JSON fields are properly handled
            // Handle NULL values and ensure arrays
            if ($audit->old_values === null || !is_array($audit->old_values)) {
                $audit->old_values = [];
            }
            if ($audit->new_values === null || !is_array($audit->new_values)) {
                $audit->new_values = [];
            }
            if ($audit->metadata === null || !is_array($audit->metadata)) {
                $audit->metadata = [];
            }
            
            return view('backend.admin.audit.show', compact('audit'));
        } catch (\Exception $e) {
            \Log::error('Error loading audit trail details', [
                'audit_id' => $audit,
                'tenant_id' => request()->tenant->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with('error', 'Error loading audit trail details: ' . $e->getMessage());
        }
    }

    /**
     * Get audit statistics
     */
    public function statistics(Request $request)
    {
        $query = AuditTrail::where('tenant_id', request()->tenant->id);

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $stats = [
            'total_events' => $query->count(),
            'events_by_type' => $query->groupBy('event_type')
                ->selectRaw('event_type, count(*) as count')
                ->pluck('count', 'event_type'),
            'events_by_user_type' => $query->groupBy('user_type')
                ->selectRaw('user_type, count(*) as count')
                ->pluck('count', 'user_type'),
            'events_by_auditable_type' => $query->groupBy('auditable_type')
                ->selectRaw('auditable_type, count(*) as count')
                ->pluck('count', 'auditable_type'),
            'recent_activity' => $query->with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
               ];

        return response()->json($stats);
    }

    /**
     * Export audit trails
     */
    public function export(Request $request)
    {
        $query = AuditTrail::with(['user', 'auditable'])
            ->where('tenant_id', request()->tenant->id);

        // Apply same filters as index
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->auditable_type);
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $audits = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $filename = 'audit_trails_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($audits) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'Date/Time',
                'Event Type',
                'User Type',
                'User Name',
                'Auditable Type',
                'Auditable ID',
                'Description',
                'IP Address',
                'URL'
            ]);

            foreach ($audits as $audit) {
                fputcsv($file, [
                    $audit->created_at->format('Y-m-d H:i:s'),
                    ucfirst(str_replace('_', ' ', $audit->event_type)),
                    ucfirst($audit->user_type),
                    $audit->user ? $audit->user->name : 'System',
                    class_basename($audit->auditable_type),
                    $audit->auditable_id,
                    $audit->description,
                    $audit->ip_address,
                    $audit->url
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
