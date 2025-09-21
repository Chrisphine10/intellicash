<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanTermsAndPrivacy;
use App\Models\LoanProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LoanTermsAndPrivacyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tenant = app('tenant');
        
        // Check if Advanced Loan Management module is active
        if (!$tenant->advanced_loan_management_enabled) {
            return redirect()->route('dashboard.index')
                ->with('error', 'Terms & Privacy management is only available when Advanced Loan Management module is enabled.');
        }
        
        $assets = ['datatable'];
        $terms = LoanTermsAndPrivacy::where('tenant_id', session('tenant_id'))
            ->with(['loanProduct', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('backend.admin.loan_terms.index', compact('terms', 'assets'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $tenant = app('tenant');
        
        // Check if Advanced Loan Management module is active
        if (!$tenant->advanced_loan_management_enabled) {
            return redirect()->route('dashboard.index')
                ->with('error', 'Terms & Privacy management is only available when Advanced Loan Management module is enabled.');
        }
        
        $loanProducts = LoanProduct::where('tenant_id', session('tenant_id'))
            ->where('status', 1)
            ->get();
        
        // Get available legal templates
        $legalTemplates = \App\Models\LegalTemplate::active()
            ->orderBy('country_name')
            ->orderBy('template_name')
            ->get();
        
        return view('backend.admin.loan_terms.create', compact('loanProducts', 'legalTemplates'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'loan_product_id' => 'nullable|exists:loan_products,id',
            'terms_and_conditions' => 'required|string',
            'privacy_policy' => 'required|string',
            'version' => 'required|string|max:50',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:effective_date',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            // If this is set as default, remove default status from other terms
            if ($request->is_default) {
                LoanTermsAndPrivacy::where('tenant_id', session('tenant_id'))
                    ->where('loan_product_id', $request->loan_product_id)
                    ->update(['is_default' => false]);
            }

            $terms = new LoanTermsAndPrivacy();
            $terms->tenant_id = session('tenant_id');
            $terms->loan_product_id = $request->loan_product_id;
            $terms->title = $request->title;
            $terms->terms_and_conditions = $request->terms_and_conditions;
            $terms->privacy_policy = $request->privacy_policy;
            $terms->version = $request->version;
            $terms->effective_date = $request->effective_date;
            $terms->expiry_date = $request->expiry_date;
            $terms->is_default = $request->boolean('is_default');
            $terms->created_by = auth()->id();
            $terms->save();

            DB::commit();

            return redirect()->route('loan_terms.index')
                ->with('success', 'Terms and Privacy Policy created successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Failed to create terms: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $terms = LoanTermsAndPrivacy::where('id', $id)
            ->where('tenant_id', session('tenant_id'))
            ->with(['loanProduct', 'creator', 'updater'])
            ->firstOrFail();

        return view('backend.admin.loan_terms.show', compact('terms'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $terms = LoanTermsAndPrivacy::where('id', $id)
            ->where('tenant_id', session('tenant_id'))
            ->firstOrFail();

        $loanProducts = LoanProduct::where('tenant_id', session('tenant_id'))
            ->where('status', 1)
            ->get();

        return view('backend.admin.loan_terms.edit', compact('terms', 'loanProducts'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $terms = LoanTermsAndPrivacy::where('id', $id)
            ->where('tenant_id', session('tenant_id'))
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'loan_product_id' => 'nullable|exists:loan_products,id',
            'terms_and_conditions' => 'required|string',
            'privacy_policy' => 'required|string',
            'version' => 'required|string|max:50',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:effective_date',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            // If this is set as default, remove default status from other terms
            if ($request->is_default) {
                LoanTermsAndPrivacy::where('tenant_id', session('tenant_id'))
                    ->where('loan_product_id', $request->loan_product_id)
                    ->where('id', '!=', $id)
                    ->update(['is_default' => false]);
            }

            $terms->title = $request->title;
            $terms->loan_product_id = $request->loan_product_id;
            $terms->terms_and_conditions = $request->terms_and_conditions;
            $terms->privacy_policy = $request->privacy_policy;
            $terms->version = $request->version;
            $terms->effective_date = $request->effective_date;
            $terms->expiry_date = $request->expiry_date;
            $terms->is_default = $request->boolean('is_default');
            $terms->is_active = $request->boolean('is_active');
            $terms->updated_by = auth()->id();
            $terms->save();

            DB::commit();

            return redirect()->route('loan_terms.index')
                ->with('success', 'Terms and Privacy Policy updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Failed to update terms: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $terms = LoanTermsAndPrivacy::where('id', $id)
            ->where('tenant_id', session('tenant_id'))
            ->firstOrFail();

        // Don't allow deletion of default terms
        if ($terms->is_default) {
            return back()->with('error', 'Cannot delete default terms. Please set another as default first.');
        }

        $terms->delete();

        return redirect()->route('loan_terms.index')
            ->with('success', 'Terms and Privacy Policy deleted successfully');
    }

    /**
     * Set as default terms
     */
    public function setDefault($id)
    {
        $terms = LoanTermsAndPrivacy::where('id', $id)
            ->where('tenant_id', session('tenant_id'))
            ->firstOrFail();

        DB::beginTransaction();

        try {
            // Remove default status from other terms for the same product
            LoanTermsAndPrivacy::where('tenant_id', session('tenant_id'))
                ->where('loan_product_id', $terms->loan_product_id)
                ->update(['is_default' => false]);

            // Set this as default
            $terms->is_default = true;
            $terms->save();

            DB::commit();

            return back()->with('success', 'Terms set as default successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Failed to set as default: ' . $e->getMessage());
        }
    }

    /**
     * Toggle active status
     */
    public function toggleActive($id)
    {
        $terms = LoanTermsAndPrivacy::where('id', $id)
            ->where('tenant_id', session('tenant_id'))
            ->firstOrFail();

        $terms->is_active = !$terms->is_active;
        $terms->save();

        $status = $terms->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Terms {$status} successfully");
    }

    /**
     * Get terms for loan application (AJAX)
     */
    public function getTermsForProduct(Request $request)
    {
        $productId = $request->product_id;
        $tenantId = session('tenant_id');

        // Try to get product-specific terms first
        $terms = LoanTermsAndPrivacy::getLatestForTenantAndProduct($tenantId, $productId);

        // If no product-specific terms, get general default terms
        if (!$terms) {
            $terms = LoanTermsAndPrivacy::getDefaultForTenant($tenantId);
        }

        // If still no terms, try to get from legal templates based on tenant country
        if (!$terms) {
            $tenant = app('tenant');
            $countryCode = $this->getCountryCodeFromTenant($tenant);
            
            $legalTemplate = \App\Models\LegalTemplate::active()
                ->where('country_code', $countryCode)
                ->first();
            
            if ($legalTemplate) {
                $terms = (object) [
                    'title' => $legalTemplate->template_name,
                    'terms_and_conditions' => $legalTemplate->terms_and_conditions,
                    'privacy_policy' => $legalTemplate->privacy_policy,
                    'version' => $legalTemplate->formatted_version,
                ];
            }
        }

        if ($terms) {
            return response()->json([
                'success' => true,
                'terms' => [
                    'title' => $terms->title,
                    'terms_and_conditions' => $terms->terms_and_conditions,
                    'privacy_policy' => $terms->privacy_policy,
                    'version' => $terms->version ?? $terms->formatted_version ?? '1.0',
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No terms found for this loan product'
        ]);
    }

    /**
     * Get country code from tenant
     */
    private function getCountryCodeFromTenant($tenant)
    {
        // You can customize this based on how you store country information in your tenant model
        // For now, defaulting to Kenya
        $countryMapping = [
            'intelliwealth' => 'KEN',
            'kenya' => 'KEN',
            'uganda' => 'UGA',
            'tanzania' => 'TZA',
        ];
        
        $slug = strtolower($tenant->slug ?? '');
        return $countryMapping[$slug] ?? 'KEN';
    }

    /**
     * Get legal template details (AJAX)
     */
    public function getTemplateDetails(Request $request)
    {
        $templateId = $request->template_id;
        
        $template = \App\Models\LegalTemplate::find($templateId);
        
        if ($template) {
            return response()->json([
                'success' => true,
                'template' => [
                    'id' => $template->id,
                    'country_name' => $template->country_name,
                    'template_name' => $template->template_name,
                    'template_type' => $template->template_type,
                    'description' => $template->description,
                    'terms_and_conditions' => $template->terms_and_conditions,
                    'privacy_policy' => $template->privacy_policy,
                    'applicable_laws' => $template->applicable_laws,
                    'regulatory_bodies' => $template->regulatory_bodies,
                    'version' => $template->formatted_version,
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Template not found'
        ]);
    }

    /**
     * Get available countries (AJAX)
     */
    public function getAvailableCountries()
    {
        $countries = \App\Models\LegalTemplate::getAvailableCountries();
        
        return response()->json([
            'success' => true,
            'countries' => $countries
        ]);
    }

    /**
     * Get template types for country (AJAX)
     */
    public function getTemplateTypesForCountry(Request $request)
    {
        $countryCode = $request->country_code;
        $types = \App\Models\LegalTemplate::getTemplateTypesForCountry($countryCode);
        
        return response()->json([
            'success' => true,
            'types' => $types
        ]);
    }
}
