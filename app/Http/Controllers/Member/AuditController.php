<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use DataTables;

class AuditController extends Controller
{
    /**
     * Display audit trails for members
     */
    public function index(Request $request)
    {
        $assets = ['datatable'];
        
        // Get filter options for member's own activities
        $eventTypes = AuditTrail::where('user_type', 'member')
            ->where('user_id', Auth::user()->member->id)
            ->distinct()
            ->pluck('event_type')
            ->sort();
            
        $auditableTypes = AuditTrail::where('user_type', 'member')
            ->where('user_id', Auth::user()->member->id)
            ->distinct()
            ->pluck('auditable_type')
            ->sort();
        
        return view('backend.member.audit.index', compact('assets', 'eventTypes', 'auditableTypes'));
    }

    /**
     * Get member's audit trails data for DataTable
     */
    public function getTableData(Request $request)
    {
        $query = AuditTrail::with(['user', 'auditable'])
            ->where('user_type', 'member')
            ->where('user_id', Auth::user()->member->id)
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->auditable_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return DataTables::eloquent($query)
            ->editColumn('auditable_name', function ($audit) {
                if ($audit->auditable) {
                    $modelName = class_basename($audit->auditable_type);
                    switch ($modelName) {
                        case 'Transaction':
                            return "Transaction #{$audit->auditable_id} - {$audit->auditable->type}";
                        case 'SavingsAccount':
                            return "Account: {$audit->auditable->account_number}";
                        case 'Loan':
                            return "Loan #{$audit->auditable_id}";
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
                    'login' => 'success',
                    'logout' => 'secondary',
                    default => 'secondary'
                };
                return '<span class="badge badge-' . $badgeClass . '">' . ucfirst(str_replace('_', ' ', $audit->event_type)) . '</span>';
            })
            ->editColumn('created_at', function ($audit) {
                return $audit->created_at->format('Y-m-d H:i:s');
            })
            ->addColumn('changes', function ($audit) {
                if ($audit->changes_summary) {
                    $changes = collect($audit->changes_summary)->take(2);
                    $html = '<ul class="list-unstyled mb-0">';
                    foreach ($changes as $field => $change) {
                        $html .= '<li><small>' . ucfirst(str_replace('_', ' ', $field)) . ': ' . 
                                $change['old'] . ' → ' . $change['new'] . '</small></li>';
                    }
                    if (count($audit->changes_summary) > 2) {
                        $html .= '<li><small>... and ' . (count($audit->changes_summary) - 2) . ' more</small></li>';
                    }
                    $html .= '</ul>';
                    return $html;
                }
                return '-';
            })
            ->addColumn('action', function ($audit) {
                return '<a href="' . route('member.audit.show', $audit->id) . '" class="btn btn-sm btn-info" data-title="' . _lang('Activity Details') . '">
                    <i class="fas fa-eye"></i> ' . _lang('View') . '
                </a>';
            })
            ->rawColumns(['event_type', 'changes', 'action'])
            ->make(true);
    }

    /**
     * Show member's audit trail details
     */
    public function show($id)
    {
        $audit = AuditTrail::with(['user', 'auditable'])
            ->where('user_type', 'member')
            ->where('user_id', Auth::user()->member->id)
            ->findOrFail($id);
        
        return view('backend.member.audit.show', compact('audit'));
    }

    /**
     * Get member's activity summary
     */
    public function summary(Request $request)
    {
        $query = AuditTrail::where('user_type', 'member')
            ->where('user_id', Auth::user()->member->id);

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $summary = [
            'total_activities' => $query->count(),
            'activities_by_type' => $query->groupBy('event_type')
                ->selectRaw('event_type, count(*) as count')
                ->pluck('count', 'event_type'),
            'recent_activities' => $query->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
        ];

        return response()->json($summary);
    }
}
