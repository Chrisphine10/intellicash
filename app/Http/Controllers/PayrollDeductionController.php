<?php

namespace App\Http\Controllers;

use App\Models\PayrollDeduction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PayrollDeductionController extends Controller
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
        
        $deductions = PayrollDeduction::where('tenant_id', $tenant->id)
            ->with(['createdBy'])
            ->orderBy('name')
            ->paginate(20);

        return view('backend.admin.payroll.deductions.index', compact('deductions'));
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

        $types = PayrollDeduction::getTypes();
        $taxCategories = PayrollDeduction::getTaxCategories();

        return view('backend.admin.payroll.deductions.create', compact('types', 'taxCategories'));
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

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:payroll_deductions,code',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:percentage,fixed_amount,tiered',
            'rate' => 'nullable|numeric|min:0|max:100',
            'amount' => 'nullable|numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'maximum_amount' => 'nullable|numeric|min:0',
            'is_mandatory' => 'boolean',
            'tax_category' => 'nullable|string|max:255',
        ]);

        // Additional validation for business rules
        $validator->after(function ($validator) use ($request) {
            // Validate rate based on type
            if ($request->type === 'percentage' && (!$request->rate || $request->rate < 0 || $request->rate > 100)) {
                $validator->errors()->add('rate', 'Percentage rate must be between 0 and 100');
            }
            
            if ($request->type === 'fixed_amount' && (!$request->amount || $request->amount < 0)) {
                $validator->errors()->add('amount', 'Fixed amount must be greater than 0');
            }
            
            // Validate min/max amounts
            if ($request->minimum_amount && $request->maximum_amount && $request->minimum_amount > $request->maximum_amount) {
                $validator->errors()->add('minimum_amount', 'Minimum amount cannot be greater than maximum amount');
            }
            
            // Validate tiered rates if type is tiered
            if ($request->type === 'tiered' && !$request->tiered_rates) {
                $validator->errors()->add('tiered_rates', 'Tiered rates are required for tiered deduction type');
            }
        });

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        $deduction = PayrollDeduction::create([
            'tenant_id' => $tenant->id,
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'type' => $request->type,
            'rate' => $request->rate,
            'amount' => $request->amount,
            'minimum_amount' => $request->minimum_amount,
            'maximum_amount' => $request->maximum_amount,
            'is_mandatory' => $request->boolean('is_mandatory'),
            'tax_category' => $request->tax_category,
            'created_by' => auth()->id(),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('Deduction created successfully'),
                'data' => $deduction
            ]);
        }

        return redirect()->route('payroll.deductions.index')
            ->with('success', _lang('Deduction created successfully'));
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
        
        $deduction = PayrollDeduction::where('tenant_id', $tenant->id)
            ->with(['createdBy', 'employeeDeductions.employee'])
            ->findOrFail($id);

        return view('backend.admin.payroll.deductions.show', compact('deduction'));
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
        
        $deduction = PayrollDeduction::where('tenant_id', $tenant->id)->findOrFail($id);
        
        $types = PayrollDeduction::getTypes();
        $taxCategories = PayrollDeduction::getTaxCategories();

        return view('backend.admin.payroll.deductions.edit', compact('deduction', 'types', 'taxCategories'));
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
        
        $deduction = PayrollDeduction::where('tenant_id', $tenant->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:payroll_deductions,code,' . $id,
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:percentage,fixed_amount,tiered',
            'rate' => 'nullable|numeric|min:0|max:100',
            'amount' => 'nullable|numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'maximum_amount' => 'nullable|numeric|min:0',
            'is_mandatory' => 'boolean',
            'tax_category' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        $deduction->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'type' => $request->type,
            'rate' => $request->rate,
            'amount' => $request->amount,
            'minimum_amount' => $request->minimum_amount,
            'maximum_amount' => $request->maximum_amount,
            'is_mandatory' => $request->boolean('is_mandatory'),
            'tax_category' => $request->tax_category,
            'updated_by' => auth()->id(),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('Deduction updated successfully'),
                'data' => $deduction
            ]);
        }

        return redirect()->route('payroll.deductions.index')
            ->with('success', _lang('Deduction updated successfully'));
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
        
        $deduction = PayrollDeduction::where('tenant_id', $tenant->id)->findOrFail($id);

        // Check if deduction is assigned to employees
        if ($deduction->employeeDeductions()->count() > 0) {
            if (request()->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Cannot delete deduction that is assigned to employees')]);
            }
            return back()->with('error', _lang('Cannot delete deduction that is assigned to employees'));
        }

        $deduction->delete();

        if (request()->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('Deduction deleted successfully')
            ]);
        }

        return redirect()->route('payroll.deductions.index')
            ->with('success', _lang('Deduction deleted successfully'));
    }

    /**
     * Toggle deduction status
     */
    public function toggleStatus(Request $request, $tenant, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        $deduction = PayrollDeduction::where('tenant_id', $tenant->id)->findOrFail($id);
        
        $deduction->is_active = !$deduction->is_active;
        $deduction->save();

        if (request()->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('Deduction status updated successfully')
            ]);
        }

        return back()->with('success', _lang('Deduction status updated successfully'));
    }

    /**
     * Create default deductions
     */
    public function createDefaults(Request $request, $tenant)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        PayrollDeduction::createDefaultDeductions($tenant->id, auth()->id());

        if (request()->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('Default deductions created successfully')
            ]);
        }

        return back()->with('success', _lang('Default deductions created successfully'));
    }
}