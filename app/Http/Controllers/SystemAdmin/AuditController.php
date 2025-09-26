<?php

namespace App\Http\Controllers\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\Tenant;
use Illuminate\Http\Request;
use DataTables;

class AuditController extends Controller
{
    /**
     * Display audit trails for system admin
     */
    public function index(Request $request)
    {
        $assets = ['datatable'];
        
        // Get filter options
        $eventTypes = AuditTrail::distinct()->pluck('event_type')->sort();
        $auditableTypes = AuditTrail::distinct()->pluck('auditable_type')->sort();
        $userTypes = AuditTrail::distinct()->pluck('user_type')->sort();
        $tenants = Tenant::select('id', 'name')->get();
        
        return view('backend.system_admin.audit.index', compact('assets', 'eventTypes', 'auditableTypes', 'userTypes', 'tenants'));
    }

    /**
     * Get audit trails data for DataTable (system-wide)
     */
    public function getTableData(Request $request)
    {
        $query = AuditTrail::with(['user', 'auditable', 'tenant'])
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

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
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
            ->editColumn('tenant_name', function ($audit) {
                return $audit->tenant ? $audit->tenant->name : 'N/A';
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
                        case 'Tenant':
                            return "Tenant: {$audit->auditable->name}";
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
                    'tenant_created' => 'success',
                    'tenant_updated' => 'warning',
                    'tenant_deleted' => 'danger',
                    default => 'secondary'
                };
                return '<span class="badge badge-' . $badgeClass . '">' . ucfirst(str_replace('_', ' ', $audit->event_type)) . '</span>';
            })
            ->editColumn('created_at', function ($audit) {
                return $audit->created_at->format('Y-m-d H:i:s');
            })
            ->addColumn('changes', function ($audit) {
                if ($audit->changes_summary) {
                    $changes = collect($audit->changes_summary)->take(3);
                    $html = '<ul class="list-unstyled mb-0">';
                    foreach ($changes as $field => $change) {
                        $html .= '<li><small>' . ucfirst(str_replace('_', ' ', $field)) . ': ' . 
                                $change['old'] . ' → ' . $change['new'] . '</small></li>';
                    }
                    if (count($audit->changes_summary) > 3) {
                        $html .= '<li><small>... and ' . (count($audit->changes_summary) - 3) . ' more</small></li>';
                    }
                    $html .= '</ul>';
                    return $html;
                }
                return '-';
            })
            ->addColumn('action', function ($audit) {
                return '<a href="' . route('admin.audit.show', $audit->id) . '" class="btn btn-sm btn-info" data-title="' . _lang('Audit Details') . '">
                    <i class="fas fa-eye"></i> ' . _lang('View') . '
                </a>';
            })
            ->rawColumns(['event_type', 'changes', 'action'])
            ->make(true);
    }

    /**
     * Show audit trail details
     */
    public function show($id)
    {
        $audit = AuditTrail::with(['user', 'auditable', 'tenant'])->findOrFail($id);
        
        return view('backend.system_admin.audit.show', compact('audit'));
    }

    /**
     * Get system-wide audit statistics
     */
    public function statistics(Request $request)
    {
        $query = AuditTrail::query();

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

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // Calculate statistics
        $totalEvents = $query->count();
        
        // Today's events
        $todayEvents = (clone $query)->whereDate('created_at', today())->count();
        
        // This week's events (Monday to Sunday)
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();
        $weekEvents = (clone $query)->whereBetween('created_at', [$startOfWeek, $endOfWeek])->count();
        
        // This month's events
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        $monthEvents = (clone $query)->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();

        // Events by type
        $eventsByType = (clone $query)->groupBy('event_type')
            ->selectRaw('event_type, count(*) as count')
            ->pluck('count', 'event_type');

        // Events by user type
        $eventsByUserType = (clone $query)->groupBy('user_type')
            ->selectRaw('user_type, count(*) as count')
            ->pluck('count', 'user_type');

        // Events by tenant
        $eventsByTenant = (clone $query)->join('tenants', 'audit_trails.tenant_id', '=', 'tenants.id')
            ->groupBy('tenants.id', 'tenants.name')
            ->selectRaw('tenants.name, count(*) as count')
            ->pluck('count', 'tenants.name');

        // Recent activity
        $recentActivity = (clone $query)->with(['user', 'tenant'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'total_events' => $totalEvents,
            'today_events' => $todayEvents,
            'week_events' => $weekEvents,
            'month_events' => $monthEvents,
            'events_by_type' => $eventsByType,
            'events_by_user_type' => $eventsByUserType,
            'events_by_tenant' => $eventsByTenant,
            'recent_activity' => $recentActivity
        ]);
    }

    /**
     * Export audit trails
     */
    public function export(Request $request)
    {
        $query = AuditTrail::with(['user', 'auditable', 'tenant']);

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

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $audits = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $filename = 'system_audit_trails_' . date('Y-m-d_H-i-s') . '.csv';
        
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
                'Tenant',
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
                    $audit->tenant ? $audit->tenant->name : 'N/A',
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
