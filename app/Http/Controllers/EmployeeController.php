<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollDeduction;
use App\Models\PayrollBenefit;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeBenefit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
{
    /**
     * Resolve tenant from slug if it's a string
     */
    private function resolveTenant($tenant)
    {
        if (is_string($tenant)) {
            return \App\Models\Tenant::where('slug', $tenant)->firstOrFail();
        }
        return $tenant;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $tenant)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        $employees = Employee::where('tenant_id', $tenant->id)
            ->with(['user', 'createdBy'])
            ->orderBy('first_name')
            ->paginate(20);

        $stats = [
            'total_employees' => Employee::where('tenant_id', $tenant->id)->count(),
            'active_employees' => Employee::where('tenant_id', $tenant->id)->active()->count(),
            'departments' => Employee::getDepartments($tenant->id)->count(),
        ];

        return view('backend.admin.payroll.employees.index', compact('employees', 'stats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request, $tenant)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        // Get employee account type setting
        $employeeAccountType = get_tenant_option('employee_account_type', 'system_users', $tenant->id);
        
        $users = \App\Models\User::where('tenant_id', $tenant->id)
            ->whereDoesntHave('employee')
            ->orderBy('name')
            ->get();

        $members = null;
        if ($employeeAccountType === 'member_accounts') {
            $members = \App\Models\Member::where('tenant_id', $tenant->id)
                ->whereDoesntHave('employee')
                ->orderBy('first_name')
                ->get();
        }

        $employmentTypes = Employee::getEmploymentTypes();
        $employmentStatuses = Employee::getEmploymentStatuses();
        $payFrequencies = Employee::getPayFrequencies();

        return view('backend.admin.payroll.employees.create', compact('users', 'members', 'employmentTypes', 'employmentStatuses', 'payFrequencies', 'employeeAccountType'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $tenant)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);

        // Get employee account type setting
        $employeeAccountType = get_tenant_option('employee_account_type', 'system_users', $tenant->id);
        
        $validationRules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'hire_date' => 'required|date',
            'job_title' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'employment_type' => 'required|in:full_time,part_time,contract,intern',
            'basic_salary' => 'required|numeric|min:0',
            'pay_frequency' => 'required|in:weekly,bi_weekly,monthly,quarterly,annually',
        ];

        if ($employeeAccountType === 'system_users') {
            $validationRules['user_id'] = 'nullable|exists:users,id';
        } else {
            $validationRules['member_id'] = 'nullable|exists:members,id';
        }

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        $tenant = app('tenant');

        DB::beginTransaction();
        
        try {
            $employeeData = [
                'tenant_id' => $tenant->id,
                'employee_id' => Employee::generateEmployeeId($tenant->id),
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'middle_name' => $request->middle_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'national_id' => $request->national_id,
                'hire_date' => $request->hire_date,
                'job_title' => $request->job_title,
                'department' => $request->department,
                'employment_type' => $request->employment_type,
                'basic_salary' => $request->basic_salary,
                'salary_currency' => $request->salary_currency ?? get_base_currency(),
                'pay_frequency' => $request->pay_frequency,
                'bank_name' => $request->bank_name,
                'bank_account_number' => $request->bank_account_number,
                'bank_routing_number' => $request->bank_routing_number,
                'tax_id' => $request->tax_id,
                'social_security_number' => $request->social_security_number,
                'created_by' => auth()->id(),
            ];

        // Handle account type specific fields and validate relationships
        if ($employeeAccountType === 'system_users') {
            $employeeData['user_id'] = $request->user_id;
            $employeeData['member_id'] = null; // Clear member_id when using system users
            
            // Check if user is already linked to another employee
            if ($request->user_id) {
                $existingEmployee = Employee::where('user_id', $request->user_id)
                    ->where('tenant_id', $tenant->id)
                    ->first();
                if ($existingEmployee) {
                    if ($request->ajax()) {
                        return response()->json(['result' => 'error', 'message' => _lang('This user is already linked to another employee')]);
                    }
                    return back()->with('error', _lang('This user is already linked to another employee'));
                }
            }
        } else {
            $employeeData['member_id'] = $request->member_id;
            $employeeData['user_id'] = null; // Clear user_id when using member accounts
            
            // Check if member is already linked to another employee
            if ($request->member_id) {
                $existingEmployee = Employee::where('member_id', $request->member_id)
                    ->where('tenant_id', $tenant->id)
                    ->first();
                if ($existingEmployee) {
                    if ($request->ajax()) {
                        return response()->json(['result' => 'error', 'message' => _lang('This member is already linked to another employee')]);
                    }
                    return back()->with('error', _lang('This member is already linked to another employee'));
                }
            }
        }

            $employee = Employee::create($employeeData);

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'result' => 'success',
                    'message' => _lang('Employee created successfully'),
                    'data' => $employee
                ]);
            }

            return redirect()->route('payroll.employees.show', $employee->id)
                ->with('success', _lang('Employee created successfully'));

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
    public function show(Request $request, $tenant, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        $employee = Employee::where('tenant_id', $tenant->id)
            ->with(['user', 'createdBy', 'updatedBy', 'payrollItems.payrollPeriod'])
            ->findOrFail($id);

        $payrollHistory = $employee->payrollItems()
            ->with('payrollPeriod')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('backend.admin.payroll.employees.show', compact('employee', 'payrollHistory'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, $tenant, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        $employee = Employee::where('tenant_id', $tenant->id)->findOrFail($id);
        
        // Get employee account type setting
        $employeeAccountType = get_tenant_option('employee_account_type', 'system_users', $tenant->id);
        
        $users = \App\Models\User::where('tenant_id', $tenant->id)
            ->where(function($query) use ($employee) {
                $query->whereDoesntHave('employee')
                      ->orWhere('id', $employee->user_id);
            })
            ->orderBy('name')
            ->get();

        $members = null;
        if ($employeeAccountType === 'member_accounts') {
            $members = \App\Models\Member::where('tenant_id', $tenant->id)
                ->where(function($query) use ($employee) {
                    $query->whereDoesntHave('employee')
                          ->orWhere('id', $employee->member_id);
                })
                ->orderBy('first_name')
                ->get();
        }

        $employmentTypes = Employee::getEmploymentTypes();
        $employmentStatuses = Employee::getEmploymentStatuses();
        $payFrequencies = Employee::getPayFrequencies();

        return view('backend.admin.payroll.employees.edit', compact('employee', 'users', 'members', 'employmentTypes', 'employmentStatuses', 'payFrequencies', 'employeeAccountType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $tenant, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        $employee = Employee::where('tenant_id', $tenant->id)->findOrFail($id);

        // Get employee account type setting
        $employeeAccountType = get_tenant_option('employee_account_type', 'system_users', $tenant->id);
        
        $validationRules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'hire_date' => 'required|date',
            'job_title' => 'required|string|max:255',
            'department' => 'nullable|string|max:255',
            'employment_type' => 'required|in:full_time,part_time,contract,intern',
            'basic_salary' => 'required|numeric|min:0',
            'pay_frequency' => 'required|in:weekly,bi_weekly,monthly,quarterly,annually',
        ];

        if ($employeeAccountType === 'system_users') {
            $validationRules['user_id'] = 'nullable|exists:users,id';
        } else {
            $validationRules['member_id'] = 'nullable|exists:members,id';
        }

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        $updateData = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'middle_name' => $request->middle_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'national_id' => $request->national_id,
            'hire_date' => $request->hire_date,
            'job_title' => $request->job_title,
            'department' => $request->department,
            'employment_type' => $request->employment_type,
            'basic_salary' => $request->basic_salary,
            'salary_currency' => $request->salary_currency ?? get_base_currency(),
            'pay_frequency' => $request->pay_frequency,
            'bank_name' => $request->bank_name,
            'bank_account_number' => $request->bank_account_number,
            'bank_routing_number' => $request->bank_routing_number,
            'tax_id' => $request->tax_id,
            'social_security_number' => $request->social_security_number,
            'updated_by' => auth()->id(),
        ];

        // Handle account type specific fields and validate relationships
        if ($employeeAccountType === 'system_users') {
            $updateData['user_id'] = $request->user_id;
            $updateData['member_id'] = null; // Clear member_id if switching to system users
            
            // Check if user is already linked to another employee (excluding current employee)
            if ($request->user_id) {
                $existingEmployee = Employee::where('user_id', $request->user_id)
                    ->where('tenant_id', $tenant->id)
                    ->where('id', '!=', $employee->id)
                    ->first();
                if ($existingEmployee) {
                    if ($request->ajax()) {
                        return response()->json(['result' => 'error', 'message' => _lang('This user is already linked to another employee')]);
                    }
                    return back()->with('error', _lang('This user is already linked to another employee'));
                }
            }
        } else {
            $updateData['member_id'] = $request->member_id;
            $updateData['user_id'] = null; // Clear user_id if switching to member accounts
            
            // Check if member is already linked to another employee (excluding current employee)
            if ($request->member_id) {
                $existingEmployee = Employee::where('member_id', $request->member_id)
                    ->where('tenant_id', $tenant->id)
                    ->where('id', '!=', $employee->id)
                    ->first();
                if ($existingEmployee) {
                    if ($request->ajax()) {
                        return response()->json(['result' => 'error', 'message' => _lang('This member is already linked to another employee')]);
                    }
                    return back()->with('error', _lang('This member is already linked to another employee'));
                }
            }
        }

        $employee->update($updateData);

        if ($request->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('Employee updated successfully'),
                'data' => $employee
            ]);
        }

        return redirect()->route('payroll.employees.show', $employee->id)
            ->with('success', _lang('Employee updated successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $tenant, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        $employee = Employee::where('tenant_id', $tenant->id)->findOrFail($id);

        // Check if employee has payroll history
        if ($employee->payrollItems()->count() > 0) {
            if (request()->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Cannot delete employee with payroll history')]);
            }
            return back()->with('error', _lang('Cannot delete employee with payroll history'));
        }

        $employee->delete();

        if (request()->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('Employee deleted successfully')
            ]);
        }

        return redirect()->route('payroll.employees.index')
            ->with('success', _lang('Employee deleted successfully'));
    }

    /**
     * Toggle employee status
     */
    public function toggleStatus(Request $request, $tenant, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        $employee = Employee::where('tenant_id', $tenant->id)->findOrFail($id);
        
        $employee->is_active = !$employee->is_active;
        $employee->save();

        if (request()->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('Employee status updated successfully')
            ]);
        }

        return back()->with('success', _lang('Employee status updated successfully'));
    }

    /**
     * Show employee payroll history
     */
    public function payrollHistory(Request $request, $tenant, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        $employee = Employee::where('tenant_id', $tenant->id)->findOrFail($id);
        
        $payrollHistory = $employee->payrollItems()
            ->with('payrollPeriod')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('backend.admin.payroll.employees.payroll-history', compact('employee', 'payrollHistory'));
    }

    /**
     * Show employee deductions
     */
    public function deductions(Request $request, $tenant, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        $employee = Employee::where('tenant_id', $tenant->id)->findOrFail($id);
        
        $deductions = PayrollDeduction::where('tenant_id', $tenant->id)->active()->get();
        $employeeDeductions = $employee->employeeDeductions()->with('deduction')->get();

        return view('backend.admin.payroll.employees.deductions', compact('employee', 'deductions', 'employeeDeductions'));
    }

    /**
     * Assign deductions to employee
     */
    public function assignDeductions(Request $request, $tenant, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        $employee = Employee::where('tenant_id', $tenant->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'deduction_ids' => 'nullable|array',
            'deduction_ids.*' => 'nullable|exists:payroll_deductions,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        DB::beginTransaction();
        
        try {
            // Remove existing assignments
            $employee->employeeDeductions()->delete();
            
            // Add new assignments (only if deduction_ids is provided and not empty)
            if ($request->has('deduction_ids') && is_array($request->deduction_ids)) {
                // Filter out empty values
                $validDeductionIds = array_filter($request->deduction_ids, function($id) {
                    return !empty($id) && is_numeric($id);
                });
                
                foreach ($validDeductionIds as $deductionId) {
                    EmployeeDeduction::assignDeductionToEmployee($employee->id, $deductionId);
                }
            }

            DB::commit();

            return back()->with('success', _lang('Deductions assigned successfully'));

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred: ') . $e->getMessage());
        }
    }

    /**
     * Show employee benefits
     */
    public function benefits(Request $request, $tenant, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        $employee = Employee::where('tenant_id', $tenant->id)->findOrFail($id);
        
        $benefits = PayrollBenefit::where('tenant_id', $tenant->id)->active()->get();
        $employeeBenefits = $employee->employeeBenefits()->with('benefit')->get();

        return view('backend.admin.payroll.employees.benefits', compact('employee', 'benefits', 'employeeBenefits'));
    }

    /**
     * Assign benefits to employee
     */
    public function assignBenefits(Request $request, $tenant, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        $employee = Employee::where('tenant_id', $tenant->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'benefit_ids' => 'nullable|array',
            'benefit_ids.*' => 'nullable|exists:payroll_benefits,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        DB::beginTransaction();
        
        try {
            // Remove existing assignments
            $employee->employeeBenefits()->delete();
            
            // Add new assignments (only if benefit_ids is provided and not empty)
            if ($request->has('benefit_ids') && is_array($request->benefit_ids)) {
                // Filter out empty values
                $validBenefitIds = array_filter($request->benefit_ids, function($id) {
                    return !empty($id) && is_numeric($id);
                });
                
                foreach ($validBenefitIds as $benefitId) {
                    EmployeeBenefit::assignBenefitToEmployee($employee->id, $benefitId);
                }
            }

            DB::commit();

            return back()->with('success', _lang('Benefits assigned successfully'));

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', _lang('An error occurred: ') . $e->getMessage());
        }
    }
}