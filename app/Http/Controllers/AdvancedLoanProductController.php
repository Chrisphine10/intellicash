<?php

namespace App\Http\Controllers;

use App\Models\LoanProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use DataTables;

class AdvancedLoanProductController extends Controller
{
    /**
     * Display a listing of loan products
     */
    public function index()
    {
        $assets = ['datatable'];
        return view('backend.admin.advanced_loan_management.products.index', compact('assets'));
    }

    /**
     * Get products data for DataTable
     */
    public function getProductsData(Request $request)
    {
        $tenant = app('tenant');
        
        $products = LoanProduct::where('tenant_id', $tenant->id)
            ->with(['creator', 'updater']);

        return DataTables::eloquent($products)
            ->editColumn('name', function ($product) {
                return '<a href="' . route('advanced_loan_management.products.show', $product->id) . '">' . $product->name . '</a>';
            })
            ->editColumn('minimum_amount', function ($product) {
                return 'KES ' . number_format($product->minimum_amount, 0);
            })
            ->editColumn('maximum_amount', function ($product) {
                return 'KES ' . number_format($product->maximum_amount, 0);
            })
            ->editColumn('interest_rate', function ($product) {
                return $product->interest_rate . '% ' . ucfirst($product->interest_type);
            })
            ->editColumn('term', function ($product) {
                return $product->term . ' ' . ucfirst($product->term_period) . 's';
            })
            ->editColumn('status', function ($product) {
                $badgeClass = $product->status ? 'success' : 'danger';
                $status = $product->status ? 'Active' : 'Inactive';
                return '<span class="badge badge-' . $badgeClass . '">' . $status . '</span>';
            })
            ->addColumn('applications_count', function ($product) {
                return $product->advancedLoanApplications()->count();
            })
            ->addColumn('actions', function ($product) {
                $actions = '<div class="dropdown">';
                $actions .= '<button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">Actions</button>';
                $actions .= '<div class="dropdown-menu">';
                $actions .= '<a class="dropdown-item" href="' . route('advanced_loan_management.products.show', $product->id) . '">View Details</a>';
                $actions .= '<a class="dropdown-item" href="' . route('advanced_loan_management.products.edit', $product->id) . '">Edit</a>';
                $actions .= '<a class="dropdown-item" href="' . route('advanced_loan_management.products.applications', $product->id) . '">View Applications</a>';
                
                if ($product->is_active) {
                    $actions .= '<a class="dropdown-item deactivate-product" href="#" data-id="' . $product->id . '">Deactivate</a>';
                } else {
                    $actions .= '<a class="dropdown-item activate-product" href="#" data-id="' . $product->id . '">Activate</a>';
                }
                
                $actions .= '</div></div>';
                return $actions;
            })
            ->rawColumns(['name', 'status', 'actions'])
            ->make(true);
    }

    /**
     * Show the form for creating a new product
     */
    public function create()
    {
        return view('backend.admin.advanced_loan_management.products.create');
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'minimum_amount' => 'required|numeric|min:1000',
            'maximum_amount' => 'required|numeric|min:1000|gte:minimum_amount',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'interest_type' => 'required|in:fixed,variable,flat',
            'term_min_months' => 'required|integer|min:1',
            'term_max_months' => 'required|integer|min:1|gte:term_min_months',
            'repayment_frequency' => 'required|in:weekly,monthly,quarterly,annually',
            'processing_fee' => 'required|numeric|min:0',
            'processing_fee_type' => 'required|in:fixed,percentage',
            'application_fee' => 'required|numeric|min:0',
            'application_fee_type' => 'required|in:fixed,percentage',
            'late_payment_fee' => 'required|numeric|min:0',
            'early_repayment_fee' => 'required|numeric|min:0',
            'minimum_age' => 'required|integer|min:18',
            'maximum_age' => 'required|integer|min:18|gte:minimum_age',
            'minimum_monthly_income' => 'nullable|numeric|min:0',
            'minimum_business_months' => 'required|integer|min:0',
            'requires_collateral' => 'boolean',
            'requires_guarantor' => 'boolean',
            'minimum_guarantors' => 'required|integer|min:0',
            'maximum_guarantors' => 'required|integer|min:0|gte:minimum_guarantors',
            'accepted_collateral_types' => 'nullable|array',
            'minimum_collateral_value' => 'nullable|numeric|min:0',
            'collateral_valuation_method' => 'required|in:market_value,forced_sale_value,book_value',
            'risk_level' => 'required|in:low,medium,high',
            'maximum_loan_to_value_ratio' => 'required|integer|min:1|max:100',
            'maximum_debt_to_income_ratio' => 'required|integer|min:1|max:100',
            'auto_approval_enabled' => 'boolean',
            'auto_approval_limit' => 'nullable|numeric|min:0',
            'approval_levels' => 'required|integer|min:1|max:5',
            'requires_business_plan' => 'boolean',
            'requires_financial_statements' => 'boolean',
            'requires_bank_statements' => 'boolean',
            'bank_statement_months' => 'required|integer|min:1|max:24',
            'requires_tax_certificates' => 'boolean',
            'requires_business_registration' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            LoanProduct::create([
                'tenant_id' => auth()->user()->tenant_id,
                'name' => $request->name,
                'description' => $request->description,
                'minimum_amount' => $request->minimum_amount,
                'maximum_amount' => $request->maximum_amount,
                'interest_rate' => $request->interest_rate,
                'interest_type' => $request->interest_type,
                'term_min_months' => $request->term_min_months,
                'term_max_months' => $request->term_max_months,
                'repayment_frequency' => $request->repayment_frequency,
                'processing_fee' => $request->processing_fee,
                'processing_fee_type' => $request->processing_fee_type,
                'application_fee' => $request->application_fee,
                'application_fee_type' => $request->application_fee_type,
                'late_payment_fee' => $request->late_payment_fee,
                'early_repayment_fee' => $request->early_repayment_fee,
                'minimum_age' => $request->minimum_age,
                'maximum_age' => $request->maximum_age,
                'minimum_monthly_income' => $request->minimum_monthly_income,
                'minimum_business_months' => $request->minimum_business_months,
                'requires_collateral' => $request->boolean('requires_collateral'),
                'requires_guarantor' => $request->boolean('requires_guarantor'),
                'minimum_guarantors' => $request->minimum_guarantors,
                'maximum_guarantors' => $request->maximum_guarantors,
                'accepted_collateral_types' => $request->accepted_collateral_types,
                'minimum_collateral_value' => $request->minimum_collateral_value,
                'collateral_valuation_method' => $request->collateral_valuation_method,
                'risk_level' => $request->risk_level,
                'maximum_loan_to_value_ratio' => $request->maximum_loan_to_value_ratio,
                'maximum_debt_to_income_ratio' => $request->maximum_debt_to_income_ratio,
                'auto_approval_enabled' => $request->boolean('auto_approval_enabled'),
                'auto_approval_limit' => $request->auto_approval_limit,
                'approval_levels' => $request->approval_levels,
                'requires_business_plan' => $request->boolean('requires_business_plan'),
                'requires_financial_statements' => $request->boolean('requires_financial_statements'),
                'requires_bank_statements' => $request->boolean('requires_bank_statements'),
                'bank_statement_months' => $request->bank_statement_months,
                'requires_tax_certificates' => $request->boolean('requires_tax_certificates'),
                'requires_business_registration' => $request->boolean('requires_business_registration'),
                'is_active' => $request->boolean('is_active'),
                'created_user_id' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('advanced_loan_management.products.index')
                ->with('success', 'Loan product created successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Failed to create loan product: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified product
     */
    public function show($id)
    {
        $tenant = app('tenant');
        
        $product = LoanProduct::where('tenant_id', $tenant->id)
            ->with(['creator', 'updater', 'applications'])
            ->findOrFail($id);

        return view('backend.admin.advanced_loan_management.products.show', compact('product'));
    }

    /**
     * Show the form for editing the product
     */
    public function edit($id)
    {
        $tenant = app('tenant');
        
        $product = LoanProduct::where('tenant_id', $tenant->id)->findOrFail($id);

        return view('backend.admin.advanced_loan_management.products.edit', compact('product'));
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, $id)
    {
        $tenant = app('tenant');
        
        $product = LoanProduct::where('tenant_id', $tenant->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'minimum_amount' => 'required|numeric|min:1000',
            'maximum_amount' => 'required|numeric|min:1000|gte:minimum_amount',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'interest_type' => 'required|in:fixed,variable,flat',
            'term_min_months' => 'required|integer|min:1',
            'term_max_months' => 'required|integer|min:1|gte:term_min_months',
            'repayment_frequency' => 'required|in:weekly,monthly,quarterly,annually',
            'processing_fee' => 'required|numeric|min:0',
            'processing_fee_type' => 'required|in:fixed,percentage',
            'application_fee' => 'required|numeric|min:0',
            'application_fee_type' => 'required|in:fixed,percentage',
            'late_payment_fee' => 'required|numeric|min:0',
            'early_repayment_fee' => 'required|numeric|min:0',
            'minimum_age' => 'required|integer|min:18',
            'maximum_age' => 'required|integer|min:18|gte:minimum_age',
            'minimum_monthly_income' => 'nullable|numeric|min:0',
            'minimum_business_months' => 'required|integer|min:0',
            'requires_collateral' => 'boolean',
            'requires_guarantor' => 'boolean',
            'minimum_guarantors' => 'required|integer|min:0',
            'maximum_guarantors' => 'required|integer|min:0|gte:minimum_guarantors',
            'accepted_collateral_types' => 'nullable|array',
            'minimum_collateral_value' => 'nullable|numeric|min:0',
            'collateral_valuation_method' => 'required|in:market_value,forced_sale_value,book_value',
            'risk_level' => 'required|in:low,medium,high',
            'maximum_loan_to_value_ratio' => 'required|integer|min:1|max:100',
            'maximum_debt_to_income_ratio' => 'required|integer|min:1|max:100',
            'auto_approval_enabled' => 'boolean',
            'auto_approval_limit' => 'nullable|numeric|min:0',
            'approval_levels' => 'required|integer|min:1|max:5',
            'requires_business_plan' => 'boolean',
            'requires_financial_statements' => 'boolean',
            'requires_bank_statements' => 'boolean',
            'bank_statement_months' => 'required|integer|min:1|max:24',
            'requires_tax_certificates' => 'boolean',
            'requires_business_registration' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $product->update([
                'name' => $request->name,
                'description' => $request->description,
                'minimum_amount' => $request->minimum_amount,
                'maximum_amount' => $request->maximum_amount,
                'interest_rate' => $request->interest_rate,
                'interest_type' => $request->interest_type,
                'term_min_months' => $request->term_min_months,
                'term_max_months' => $request->term_max_months,
                'repayment_frequency' => $request->repayment_frequency,
                'processing_fee' => $request->processing_fee,
                'processing_fee_type' => $request->processing_fee_type,
                'application_fee' => $request->application_fee,
                'application_fee_type' => $request->application_fee_type,
                'late_payment_fee' => $request->late_payment_fee,
                'early_repayment_fee' => $request->early_repayment_fee,
                'minimum_age' => $request->minimum_age,
                'maximum_age' => $request->maximum_age,
                'minimum_monthly_income' => $request->minimum_monthly_income,
                'minimum_business_months' => $request->minimum_business_months,
                'requires_collateral' => $request->boolean('requires_collateral'),
                'requires_guarantor' => $request->boolean('requires_guarantor'),
                'minimum_guarantors' => $request->minimum_guarantors,
                'maximum_guarantors' => $request->maximum_guarantors,
                'accepted_collateral_types' => $request->accepted_collateral_types,
                'minimum_collateral_value' => $request->minimum_collateral_value,
                'collateral_valuation_method' => $request->collateral_valuation_method,
                'risk_level' => $request->risk_level,
                'maximum_loan_to_value_ratio' => $request->maximum_loan_to_value_ratio,
                'maximum_debt_to_income_ratio' => $request->maximum_debt_to_income_ratio,
                'auto_approval_enabled' => $request->boolean('auto_approval_enabled'),
                'auto_approval_limit' => $request->auto_approval_limit,
                'approval_levels' => $request->approval_levels,
                'requires_business_plan' => $request->boolean('requires_business_plan'),
                'requires_financial_statements' => $request->boolean('requires_financial_statements'),
                'requires_bank_statements' => $request->boolean('requires_bank_statements'),
                'bank_statement_months' => $request->bank_statement_months,
                'requires_tax_certificates' => $request->boolean('requires_tax_certificates'),
                'requires_business_registration' => $request->boolean('requires_business_registration'),
                'is_active' => $request->boolean('is_active'),
                'updated_user_id' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('advanced_loan_management.products.index')
                ->with('success', 'Loan product updated successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Failed to update loan product: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Toggle product active status
     */
    public function toggleStatus(Request $request, $id)
    {
        $tenant = app('tenant');
        
        $product = LoanProduct::where('tenant_id', $tenant->id)->findOrFail($id);

        $product->update([
            'status' => !$product->status,
        ]);

        $status = $product->status ? 'activated' : 'deactivated';

        return response()->json(['success' => true, 'message' => "Product {$status} successfully."]);
    }

    /**
     * Show applications for a specific product
     */
    public function applications($id)
    {
        $tenant = app('tenant');
        
        $product = LoanProduct::where('tenant_id', $tenant->id)->findOrFail($id);
        
        $assets = ['datatable'];
        
        return view('backend.admin.advanced_loan_management.products.applications', compact('product', 'assets'));
    }

    /**
     * Get applications data for a specific product
     */
    public function getProductApplicationsData(Request $request, $id)
    {
        $tenant = app('tenant');
        
        $product = LoanProduct::where('tenant_id', $tenant->id)->findOrFail($id);
        
        $applications = $product->applications()
            ->with(['applicant', 'reviewer', 'approver']);

        return DataTables::eloquent($applications)
            ->editColumn('application_number', function ($application) {
                return '<a href="' . route('advanced_loan_management.applications.show', $application->id) . '">' . $application->application_number . '</a>';
            })
            ->editColumn('applicant_name', function ($application) {
                return $application->applicant_name . '<br><small class="text-muted">' . $application->applicant_email . '</small>';
            })
            ->editColumn('requested_amount', function ($application) {
                return 'KES ' . number_format($application->requested_amount, 0);
            })
            ->editColumn('approved_amount', function ($application) {
                return $application->approved_amount ? 'KES ' . number_format($application->approved_amount, 0) : '-';
            })
            ->editColumn('status', function ($application) {
                $badgeClass = match($application->status) {
                    'draft' => 'secondary',
                    'submitted' => 'warning',
                    'under_review' => 'info',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    'cancelled' => 'dark',
                    'disbursed' => 'primary',
                    default => 'secondary'
                };
                
                return '<span class="badge badge-' . $badgeClass . '">' . $application->status_label . '</span>';
            })
            ->editColumn('application_date', function ($application) {
                return $application->application_date->format('M d, Y');
            })
            ->addColumn('actions', function ($application) {
                $actions = '<div class="dropdown">';
                $actions .= '<button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">Actions</button>';
                $actions .= '<div class="dropdown-menu">';
                $actions .= '<a class="dropdown-item" href="' . route('advanced_loan_management.applications.show', $application->id) . '">View Details</a>';
                
                if ($application->canBeEdited()) {
                    $actions .= '<a class="dropdown-item" href="' . route('advanced_loan_management.applications.edit', $application->id) . '">Edit</a>';
                }
                
                $actions .= '</div></div>';
                return $actions;
            })
            ->rawColumns(['application_number', 'applicant_name', 'status', 'actions'])
            ->make(true);
    }
}
