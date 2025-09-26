<?php

namespace App\Http\Controllers;

use App\Models\PayrollBenefit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PayrollBenefitController extends Controller
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
        
        $benefits = PayrollBenefit::where('tenant_id', $tenant->id)
            ->with(['createdBy'])
            ->orderBy('name')
            ->paginate(20);

        return view('backend.admin.payroll.benefits.index', compact('benefits'));
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

        $types = PayrollBenefit::getTypes();
        $categories = PayrollBenefit::getCategories();

        return view('backend.admin.payroll.benefits.create', compact('types', 'categories'));
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
            'code' => 'required|string|max:10|unique:payroll_benefits,code',
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:percentage,fixed_amount,tiered',
            'rate' => 'nullable|numeric|min:0|max:100',
            'amount' => 'nullable|numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'maximum_amount' => 'nullable|numeric|min:0',
            'is_employer_paid' => 'boolean',
            'category' => 'nullable|string|max:255',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:effective_date',
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
                $validator->errors()->add('tiered_rates', 'Tiered rates are required for tiered benefit type');
            }
            
            // Validate effective and expiry dates
            if ($request->effective_date && $request->expiry_date) {
                $effectiveDate = \Carbon\Carbon::parse($request->effective_date);
                $expiryDate = \Carbon\Carbon::parse($request->expiry_date);
                
                if ($expiryDate->diffInDays($effectiveDate) < 0) {
                    $validator->errors()->add('expiry_date', 'Expiry date must be after effective date');
                }
            }
        });

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        $benefit = PayrollBenefit::create([
            'tenant_id' => $tenant->id,
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'type' => $request->type,
            'rate' => $request->rate,
            'amount' => $request->amount,
            'minimum_amount' => $request->minimum_amount,
            'maximum_amount' => $request->maximum_amount,
            'is_employer_paid' => $request->boolean('is_employer_paid'),
            'category' => $request->category,
            'effective_date' => $request->effective_date,
            'expiry_date' => $request->expiry_date,
            'created_by' => auth()->id(),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('Benefit created successfully'),
                'data' => $benefit
            ]);
        }

        return redirect()->route('payroll.benefits.index')
            ->with('success', _lang('Benefit created successfully'));
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
        
        $benefit = PayrollBenefit::where('tenant_id', $tenant->id)
            ->with(['createdBy', 'employeeBenefits.employee'])
            ->findOrFail($id);

        return view('backend.admin.payroll.benefits.show', compact('benefit'));
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
        
        $benefit = PayrollBenefit::where('tenant_id', $tenant->id)->findOrFail($id);
        
        $types = PayrollBenefit::getTypes();
        $categories = PayrollBenefit::getCategories();

        return view('backend.admin.payroll.benefits.edit', compact('benefit', 'types', 'categories'));
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
        
        $benefit = PayrollBenefit::where('tenant_id', $tenant->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:payroll_benefits,code,' . $id,
            'description' => 'nullable|string|max:1000',
            'type' => 'required|in:percentage,fixed_amount,tiered',
            'rate' => 'nullable|numeric|min:0|max:100',
            'amount' => 'nullable|numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'maximum_amount' => 'nullable|numeric|min:0',
            'is_employer_paid' => 'boolean',
            'category' => 'nullable|string|max:255',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:effective_date',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }
            return back()->withErrors($validator)->withInput();
        }

        $benefit->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'type' => $request->type,
            'rate' => $request->rate,
            'amount' => $request->amount,
            'minimum_amount' => $request->minimum_amount,
            'maximum_amount' => $request->maximum_amount,
            'is_employer_paid' => $request->boolean('is_employer_paid'),
            'category' => $request->category,
            'effective_date' => $request->effective_date,
            'expiry_date' => $request->expiry_date,
            'updated_by' => auth()->id(),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('Benefit updated successfully'),
                'data' => $benefit
            ]);
        }

        return redirect()->route('payroll.benefits.index')
            ->with('success', _lang('Benefit updated successfully'));
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
        
        $benefit = PayrollBenefit::where('tenant_id', $tenant->id)->findOrFail($id);

        // Check if benefit is assigned to employees
        if ($benefit->employeeBenefits()->count() > 0) {
            if (request()->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Cannot delete benefit that is assigned to employees')]);
            }
            return back()->with('error', _lang('Cannot delete benefit that is assigned to employees'));
        }

        $benefit->delete();

        if (request()->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('Benefit deleted successfully')
            ]);
        }

        return redirect()->route('payroll.benefits.index')
            ->with('success', _lang('Benefit deleted successfully'));
    }

    /**
     * Toggle benefit status
     */
    public function toggleStatus(Request $request, $tenant, $id)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        $benefit = PayrollBenefit::where('tenant_id', $tenant->id)->findOrFail($id);
        
        $benefit->is_active = !$benefit->is_active;
        $benefit->save();

        if (request()->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('Benefit status updated successfully')
            ]);
        }

        return back()->with('success', _lang('Benefit status updated successfully'));
    }

    /**
     * Create default benefits
     */
    public function createDefaults(Request $request, $tenant)
    {
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }

        $tenant = $this->resolveTenant($tenant);
        
        PayrollBenefit::createDefaultBenefits($tenant->id, auth()->id());

        if (request()->ajax()) {
            return response()->json([
                'result' => 'success',
                'message' => _lang('Default benefits created successfully')
            ]);
        }

        return back()->with('success', _lang('Default benefits created successfully'));
    }
}