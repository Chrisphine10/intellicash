<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollItem;
use App\Models\PayrollDeduction;
use App\Models\PayrollBenefit;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeBenefit;
use App\Models\PayrollAuditLog;

class PayrollController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = app('tenant');
        
        $payrollPeriods = PayrollPeriod::where('tenant_id', $tenant->id)
            ->with(['payrollItems', 'processedBy'])
            ->orderBy('start_date', 'desc')
            ->paginate(20);

        $stats = [
            'total_periods' => PayrollPeriod::where('tenant_id', $tenant->id)->count(),
            'active_periods' => PayrollPeriod::where('tenant_id', $tenant->id)->whereIn('status', ['draft', 'processing'])->count(),
            'completed_periods' => PayrollPeriod::where('tenant_id', $tenant->id)->where('status', 'completed')->count(),
            'total_employees' => Employee::where('tenant_id', $tenant->id)->active()->count(),
            'total_gross_pay' => PayrollPeriod::where('tenant_id', $tenant->id)->sum('total_gross_pay'),
            'total_net_pay' => PayrollPeriod::where('tenant_id', $tenant->id)->sum('total_net_pay'),
        ];

        return view('backend.admin.payroll.index', compact('payrollPeriods', 'stats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = app('tenant');
        
        $employees = Employee::where('tenant_id', $tenant->id)
            ->active()
            ->orderBy('first_name')
            ->get();

        $periodTypes = PayrollPeriod::getPeriodTypes();
        $statuses = PayrollPeriod::getStatuses();

        return view('backend.admin.payroll.create', compact('employees', 'periodTypes', 'statuses'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $validator = Validator::make($request->all(), [
            'period_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'period_type' => 'required|in:weekly,bi_weekly,monthly,quarterly,annually',
            'pay_date' => 'nullable|date|after:end_date',
            'notes' => 'nullable|string|max:1000',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'exists:employees,id|integer|min:1',
        ]);

        // Additional validation for business rules
        $validator->after(function ($validator) use ($request) {
            // Validate date ranges are reasonable
            if ($request->start_date && $request->end_date) {
                $startDate = \Carbon\Carbon::parse($request->start_date);
                $endDate = \Carbon\Carbon::parse($request->end_date);
                $daysDiff = $startDate->diffInDays($endDate);
                
                // Check if period length is reasonable based on period type
                switch ($request->period_type) {
                    case 'weekly':
                        if ($daysDiff < 6 || $daysDiff > 8) {
                            $validator->errors()->add('end_date', 'Weekly period should be 7 days');
                        }
                        break;
                    case 'bi_weekly':
                        if ($daysDiff < 13 || $daysDiff > 15) {
                            $validator->errors()->add('end_date', 'Bi-weekly period should be 14 days');
                        }
                        break;
                    case 'monthly':
                        if ($daysDiff < 28 || $daysDiff > 32) {
                            $validator->errors()->add('end_date', 'Monthly period should be 28-31 days');
                        }
                        break;
                    case 'quarterly':
                        if ($daysDiff < 85 || $daysDiff > 95) {
                            $validator->errors()->add('end_date', 'Quarterly period should be 90-92 days');
                        }
                        break;
                    case 'annually':
                        if ($daysDiff < 360 || $daysDiff > 370) {
                            $validator->errors()->add('end_date', 'Annual period should be 365-366 days');
                        }
                        break;
                }
            }
            
            // Validate employee IDs belong to current tenant
            if ($request->employee_ids) {
                $tenant = app('tenant');
                $validEmployeeIds = Employee::where('tenant_id', $tenant->id)
                    ->whereIn('id', $request->employee_ids)
                    ->pluck('id')
                    ->toArray();
                
                $invalidIds = array_diff($request->employee_ids, $validEmployeeIds);
                if (!empty($invalidIds)) {
                    $validator->errors()->add('employee_ids', 'Some selected employees do not belong to your organization');
                }
            }
        });

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        $tenant = app('tenant');

        DB::beginTransaction();
        
        try {
            $payrollPeriod = PayrollPeriod::create([
                'tenant_id' => $tenant->id,
                'period_name' => $request->period_name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'period_type' => $request->period_type,
                'pay_date' => $request->pay_date,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            // Log the creation
            PayrollAuditLog::logPayrollPeriodAction(
                'created',
                $payrollPeriod,
                "Payroll period '{$payrollPeriod->period_name}' created",
                [
                    'period_type' => $payrollPeriod->period_type,
                    'start_date' => $payrollPeriod->start_date,
                    'end_date' => $payrollPeriod->end_date,
                    'employee_count' => count($request->employee_ids ?? [])
                ]
            );

            // Create payroll items for selected employees
            if ($request->employee_ids) {
                $employees = Employee::whereIn('id', $request->employee_ids)
                    ->where('tenant_id', $tenant->id)
                    ->get();

                foreach ($employees as $employee) {
                    PayrollItem::createForEmployee($employee, $payrollPeriod);
                }
            }

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'result' => 'success',
                    'message' => _lang('Payroll period created successfully'),
                    'data' => $payrollPeriod
                ]);
            }

            return redirect()->route('payroll.show', $payrollPeriod->id)
                ->with('success', _lang('Payroll period created successfully'));

        } catch (\Exception $e) {
            DB::rollback();
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $e->getMessage()]);
            }
            
            return back()->with('error', _lang('An error occurred: ') . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = app('tenant');
        
        $payrollPeriod = PayrollPeriod::where('tenant_id', $tenant->id)
            ->with(['payrollItems.employee', 'processedBy', 'createdBy'])
            ->findOrFail($id);

        $payrollItems = $payrollPeriod->payrollItems()
            ->with('employee')
            ->orderBy('employee_id')
            ->get();

        $summary = [
            'total_employees' => $payrollItems->count(),
            'total_gross_pay' => $payrollItems->sum('gross_pay'),
            'total_deductions' => $payrollItems->sum('total_deductions'),
            'total_benefits' => $payrollItems->sum('total_benefits'),
            'total_net_pay' => $payrollItems->sum('net_pay'),
        ];

        return view('backend.admin.payroll.show', compact('payrollPeriod', 'payrollItems', 'summary'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = app('tenant');
        
        $payrollPeriod = PayrollPeriod::where('tenant_id', $tenant->id)->findOrFail($id);
        
        if (!$payrollPeriod->canBeCancelled()) {
            return back()->with('error', _lang('Cannot edit completed payroll period'));
        }

        $employees = Employee::where('tenant_id', $tenant->id)
            ->active()
            ->orderBy('first_name')
            ->get();

        $periodTypes = PayrollPeriod::getPeriodTypes();
        $statuses = PayrollPeriod::getStatuses();

        return view('backend.admin.payroll.edit', compact('payrollPeriod', 'employees', 'periodTypes', 'statuses'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = app('tenant');
        
        $payrollPeriod = PayrollPeriod::where('tenant_id', $tenant->id)->findOrFail($id);
        
        if (!$payrollPeriod->canBeCancelled()) {
            return back()->with('error', _lang('Cannot edit completed payroll period'));
        }

        $validator = Validator::make($request->all(), [
            'period_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'period_type' => 'required|in:weekly,bi_weekly,monthly,quarterly,annually',
            'pay_date' => 'nullable|date|after:end_date',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        $payrollPeriod->update([
            'period_name' => $request->period_name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'period_type' => $request->period_type,
            'pay_date' => $request->pay_date,
            'notes' => $request->notes,
            'updated_by' => auth()->id(),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('Payroll period updated successfully'),
                'data' => $payrollPeriod
            ]);
        }

        return redirect()->route('payroll.show', $payrollPeriod->id)
            ->with('success', _lang('Payroll period updated successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = app('tenant');
        
        $payrollPeriod = PayrollPeriod::where('tenant_id', $tenant->id)->findOrFail($id);
        
        if (!$payrollPeriod->canBeCancelled()) {
            return back()->with('error', _lang('Cannot delete completed payroll period'));
        }

        DB::beginTransaction();
        
        try {
            // Delete payroll items first
            $payrollPeriod->payrollItems()->delete();
            
            // Delete the period
            $payrollPeriod->delete();
            
            DB::commit();

            if (request()->ajax()) {
                return response()->json([
                    'result' => 'success',
                    'message' => _lang('Payroll period deleted successfully')
                ]);
            }

        return redirect()->route('payroll.index')
            ->with('success', _lang('Payroll period deleted successfully'));

        } catch (\Exception $e) {
            DB::rollback();
            
            if (request()->ajax()) {
                return response()->json(['result' => 'error', 'message' => $e->getMessage()]);
            }
            
            return back()->with('error', _lang('An error occurred: ') . $e->getMessage());
        }
    }

    /**
     * Process payroll period
     */
    public function process($id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        // Verify CSRF token for sensitive operations
        if (!request()->hasValidSignature() && !request()->ajax()) {
            return back()->with('error', _lang('Invalid request signature'));
        }

        $tenant = app('tenant');
        
        $payrollPeriod = PayrollPeriod::where('tenant_id', $tenant->id)->findOrFail($id);
        
        if (!$payrollPeriod->canBeProcessed()) {
            return back()->with('error', _lang('Cannot process this payroll period'));
        }

        DB::beginTransaction();
        
        try {
            $result = $payrollPeriod->process();
            
            if ($result) {
                $payrollPeriod->addProcessingLog('processed', 'Payroll period processed successfully');
                
                // Log the processing action
                PayrollAuditLog::logPayrollPeriodAction(
                    'processed',
                    $payrollPeriod,
                    "Payroll period '{$payrollPeriod->period_name}' processed",
                    [
                        'total_employees' => $payrollPeriod->total_employees,
                        'total_gross_pay' => $payrollPeriod->total_gross_pay,
                        'total_net_pay' => $payrollPeriod->total_net_pay
                    ]
                );
                
                DB::commit();
                
                if (request()->ajax()) {
                    return response()->json([
                        'result' => 'success',
                        'message' => _lang('Payroll period processed successfully')
                    ]);
                }
                
                return redirect()->route('payroll.show', $payrollPeriod->id)
                    ->with('success', _lang('Payroll period processed successfully'));
            } else {
                throw new \Exception('Failed to process payroll period');
            }

        } catch (\Exception $e) {
            DB::rollback();
            
            if (request()->ajax()) {
                return response()->json(['result' => 'error', 'message' => $e->getMessage()]);
            }
            
            return back()->with('error', _lang('An error occurred: ') . $e->getMessage());
        }
    }

    /**
     * Complete payroll period
     */
    public function complete($id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        // Verify CSRF token for sensitive operations
        if (!request()->hasValidSignature() && !request()->ajax()) {
            return back()->with('error', _lang('Invalid request signature'));
        }

        $tenant = app('tenant');
        
        $payrollPeriod = PayrollPeriod::where('tenant_id', $tenant->id)->findOrFail($id);
        
        if ($payrollPeriod->status !== 'processing') {
            return back()->with('error', _lang('Can only complete processing payroll periods'));
        }

        DB::beginTransaction();
        
        try {
            $result = $payrollPeriod->complete();
            
            if ($result) {
                $payrollPeriod->addProcessingLog('completed', 'Payroll period completed successfully');
                
                DB::commit();
                
                if (request()->ajax()) {
                    return response()->json([
                        'result' => 'success',
                        'message' => _lang('Payroll period completed successfully')
                    ]);
                }
                
                return redirect()->route('payroll.show', $payrollPeriod->id)
                    ->with('success', _lang('Payroll period completed successfully'));
            } else {
                throw new \Exception('Failed to complete payroll period');
            }

        } catch (\Exception $e) {
            DB::rollback();
            
            if (request()->ajax()) {
                return response()->json(['result' => 'error', 'message' => $e->getMessage()]);
            }
            
            return back()->with('error', _lang('An error occurred: ') . $e->getMessage());
        }
    }

    /**
     * Cancel payroll period
     */
    public function cancel($id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = app('tenant');
        
        $payrollPeriod = PayrollPeriod::where('tenant_id', $tenant->id)->findOrFail($id);
        
        if (!$payrollPeriod->canBeCancelled()) {
            return back()->with('error', _lang('Cannot cancel this payroll period'));
        }

        DB::beginTransaction();
        
        try {
            $result = $payrollPeriod->cancel();
            
            if ($result) {
                $payrollPeriod->addProcessingLog('cancelled', 'Payroll period cancelled');
                
                DB::commit();
                
                if (request()->ajax()) {
                    return response()->json([
                        'result' => 'success',
                        'message' => _lang('Payroll period cancelled successfully')
                    ]);
                }
                
                return redirect()->route('payroll.show', $payrollPeriod->id)
                    ->with('success', _lang('Payroll period cancelled successfully'));
            } else {
                throw new \Exception('Failed to cancel payroll period');
            }

        } catch (\Exception $e) {
            DB::rollback();
            
            if (request()->ajax()) {
                return response()->json(['result' => 'error', 'message' => $e->getMessage()]);
            }
            
            return back()->with('error', _lang('An error occurred: ') . $e->getMessage());
        }
    }

    /**
     * Add employee to payroll period
     */
    public function addEmployee(Request $request, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        $tenant = app('tenant');
        
        $payrollPeriod = PayrollPeriod::where('tenant_id', $tenant->id)->findOrFail($id);
        
        if (!$payrollPeriod->canBeCancelled()) {
            return back()->with('error', _lang('Cannot modify completed payroll period'));
        }

        $employee = Employee::where('tenant_id', $tenant->id)
            ->where('id', $request->employee_id)
            ->firstOrFail();

        // Check if employee already has payroll item for this period
        $existingItem = PayrollItem::where('payroll_period_id', $payrollPeriod->id)
            ->where('employee_id', $employee->id)
            ->first();

        if ($existingItem) {
            return back()->with('error', _lang('Employee already added to this payroll period'));
        }

        DB::beginTransaction();
        
        try {
            PayrollItem::createForEmployee($employee, $payrollPeriod);
            
            DB::commit();
            
            if ($request->ajax()) {
                return response()->json([
                    'result' => 'success',
                    'message' => _lang('Employee added to payroll period successfully')
                ]);
            }
            
            return redirect()->route('payroll.show', $payrollPeriod->id)
                ->with('success', _lang('Employee added to payroll period successfully'));

        } catch (\Exception $e) {
            DB::rollback();
            
            if ($request()->ajax()) {
                return response()->json(['result' => 'error', 'message' => $e->getMessage()]);
            }
            
            return back()->with('error', _lang('An error occurred: ') . $e->getMessage());
        }
    }

    /**
     * Remove employee from payroll period
     */
    public function removeEmployee(Request $request, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        $tenant = app('tenant');
        
        $payrollPeriod = PayrollPeriod::where('tenant_id', $tenant->id)->findOrFail($id);
        
        if (!$payrollPeriod->canBeCancelled()) {
            return back()->with('error', _lang('Cannot modify completed payroll period'));
        }

        DB::beginTransaction();
        
        try {
            $payrollItem = PayrollItem::where('payroll_period_id', $payrollPeriod->id)
                ->where('employee_id', $request->employee_id)
                ->firstOrFail();

            $payrollItem->delete();
            
            DB::commit();
            
            if ($request->ajax()) {
                return response()->json([
                    'result' => 'success',
                    'message' => _lang('Employee removed from payroll period successfully')
                ]);
            }
            
            return redirect()->route('payroll.show', $payrollPeriod->id)
                ->with('success', _lang('Employee removed from payroll period successfully'));

        } catch (\Exception $e) {
            DB::rollback();
            
            if ($request()->ajax()) {
                return response()->json(['result' => 'error', 'message' => $e->getMessage()]);
            }
            
            return back()->with('error', _lang('An error occurred: ') . $e->getMessage());
        }
    }

    /**
     * Generate payroll report
     */
    public function report($id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = app('tenant');
        
        $payrollPeriod = PayrollPeriod::where('tenant_id', $tenant->id)
            ->with(['payrollItems.employee', 'processedBy', 'createdBy'])
            ->findOrFail($id);

        $payrollItems = $payrollPeriod->payrollItems()
            ->with('employee')
            ->orderBy('employee_id')
            ->get();

        $summary = [
            'total_employees' => $payrollItems->count(),
            'total_gross_pay' => $payrollItems->sum('gross_pay'),
            'total_deductions' => $payrollItems->sum('total_deductions'),
            'total_benefits' => $payrollItems->sum('total_benefits'),
            'total_net_pay' => $payrollItems->sum('net_pay'),
        ];

        return view('backend.admin.payroll.report', compact('payrollPeriod', 'payrollItems', 'summary'));
    }

    /**
     * Export payroll data
     */
    public function export($id, $format = 'pdf')
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = app('tenant');
        
        $payrollPeriod = PayrollPeriod::where('tenant_id', $tenant->id)
            ->with(['payrollItems.employee', 'processedBy', 'createdBy'])
            ->findOrFail($id);

        $payrollItems = $payrollPeriod->payrollItems()
            ->with('employee')
            ->orderBy('employee_id')
            ->get();

        $summary = [
            'total_employees' => $payrollItems->count(),
            'total_gross_pay' => $payrollItems->sum('gross_pay'),
            'total_deductions' => $payrollItems->sum('total_deductions'),
            'total_benefits' => $payrollItems->sum('total_benefits'),
            'total_net_pay' => $payrollItems->sum('net_pay'),
        ];

        switch ($format) {
            case 'excel':
                return $this->exportToExcel($payrollPeriod, $payrollItems, $summary);
            case 'csv':
                return $this->exportToCsv($payrollPeriod, $payrollItems, $summary);
            case 'pdf':
            default:
                return $this->exportToPdf($payrollPeriod, $payrollItems, $summary);
        }
    }

    /**
     * Export to PDF
     */
    private function exportToPdf($payrollPeriod, $payrollItems, $summary)
    {
        // Implementation for PDF export
        return response()->json([
            'result' => 'success',
            'message' => _lang('PDF export functionality will be implemented'),
            'data' => compact('payrollPeriod', 'payrollItems', 'summary')
        ]);
    }

    /**
     * Export to Excel
     */
    private function exportToExcel($payrollPeriod, $payrollItems, $summary)
    {
        // Implementation for Excel export
        return response()->json([
            'result' => 'success',
            'message' => _lang('Excel export functionality will be implemented'),
            'data' => compact('payrollPeriod', 'payrollItems', 'summary')
        ]);
    }

    /**
     * Export to CSV
     */
    private function exportToCsv($payrollPeriod, $payrollItems, $summary)
    {
        // Implementation for CSV export
        return response()->json([
            'result' => 'success',
            'message' => _lang('CSV export functionality will be implemented'),
            'data' => compact('payrollPeriod', 'payrollItems', 'summary')
        ]);
    }
}
