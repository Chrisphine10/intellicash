<?php

namespace App\Http\Controllers;

use App\Models\AdvancedLoanApplication;
use App\Models\LoanProduct;
use App\Models\Member;
use App\Models\Loan;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Transaction;
use App\Models\LoanTermsAndPrivacy;
use App\Models\LegalTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use DataTables;
use Carbon\Carbon;

class AdvancedLoanManagementController extends Controller
{
    /**
     * Display the main dashboard for Advanced Loan Management
     */
    public function index()
    {
        $tenant = app('tenant');
        
        // Check if Advanced Loan Management module is active
        if (!$tenant->advanced_loan_management_enabled) {
            return redirect()->route('dashboard.index')
                ->with('error', 'Advanced Loan Management module is not enabled for this tenant.');
        }
        
        // Get enhanced loan management statistics
        $stats = [
            'total_loans' => Loan::where('tenant_id', $tenant->id)->count(),
            'pending_loans' => Loan::where('tenant_id', $tenant->id)->where('status', 0)->count(),
            'active_loans' => Loan::where('tenant_id', $tenant->id)->where('status', 1)->count(),
            'completed_loans' => Loan::where('tenant_id', $tenant->id)->where('status', 2)->count(),
            'total_loan_products' => LoanProduct::where('tenant_id', $tenant->id)->active()->count(),
            'total_loan_amount' => Loan::where('tenant_id', $tenant->id)->sum('applied_amount'),
            'total_pending_amount' => Loan::where('tenant_id', $tenant->id)->where('status', 0)->sum('applied_amount'),
        ];


        // Get recent loans
        $recentLoans = Loan::where('tenant_id', $tenant->id)
            ->with(['borrower', 'loanProduct', 'currency'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get active loan products
        $activeProducts = LoanProduct::where('tenant_id', $tenant->id)
            ->active()
            ->orderBy('name')
            ->get();

        return view('backend.admin.advanced_loan_management.dashboard', compact('stats', 'recentLoans', 'activeProducts'));
    }

    /**
     * Display applications listing
     */
    public function applications(Request $request)
    {
        $assets = ['datatable'];
        return view('backend.admin.advanced_loan_management.applications.index', compact('assets'));
    }

    /**
     * Get applications data for DataTable
     */
    public function getApplicationsData(Request $request)
    {
        $tenant = app('tenant');
        
        $applications = AdvancedLoanApplication::where('tenant_id', $tenant->id)
            ->with(['applicant', 'loanProduct', 'reviewer', 'approver']);

        // Apply filters
        if ($request->filled('status')) {
            $applications->where('status', $request->status);
        }

        if ($request->filled('application_type')) {
            $applications->where('application_type', $request->application_type);
        }

        if ($request->filled('date_from')) {
            $applications->whereDate('application_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $applications->whereDate('application_date', '<=', $request->date_to);
        }

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
            ->editColumn('application_type', function ($application) {
                return $application->application_type_label;
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
                
                if ($application->canBeApproved()) {
                    $actions .= '<a class="dropdown-item approve-application" href="#" data-id="' . $application->id . '">Approve</a>';
                }
                
                if ($application->canBeRejected()) {
                    $actions .= '<a class="dropdown-item reject-application" href="#" data-id="' . $application->id . '">Reject</a>';
                }
                
                $actions .= '</div></div>';
                return $actions;
            })
            ->rawColumns(['application_number', 'applicant_name', 'status', 'actions'])
            ->make(true);
    }

    /**
     * Show application details
     */
    public function showApplication($id)
    {
        $tenant = app('tenant');
        
        $application = AdvancedLoanApplication::where('tenant_id', $tenant->id)
            ->with(['applicant', 'loanProduct', 'reviewer', 'approver', 'creator', 'loan'])
            ->findOrFail($id);

        return view('backend.admin.advanced_loan_management.applications.show', compact('application'));
    }

    /**
     * Show edit application form
     */
    public function editApplication($id)
    {
        $tenant = app('tenant');
        
        $application = AdvancedLoanApplication::where('tenant_id', $tenant->id)
            ->with(['applicant', 'loanProduct'])
            ->findOrFail($id);

        if (!$application->canBeEdited()) {
            return redirect()->route('advanced_loan_management.applications.show', $id)
                ->with('error', 'Application cannot be edited in current status.');
        }

        $loanProducts = LoanProduct::where('tenant_id', $tenant->id)
            ->active()
            ->orderBy('name')
            ->get();

        return view('backend.admin.advanced_loan_management.applications.edit', compact('application', 'loanProducts'));
    }

    /**
     * Update application
     */
    public function updateApplication(Request $request, $id)
    {
        $tenant = app('tenant');
        
        $application = AdvancedLoanApplication::where('tenant_id', $tenant->id)->findOrFail($id);

        if (!$application->canBeEdited()) {
            return redirect()->route('advanced_loan_management.applications.show', $id)
                ->with('error', 'Application cannot be edited in current status.');
        }

        $validator = Validator::make($request->all(), [
            'loan_product_id' => 'required|exists:advanced_loan_products,id',
            'requested_amount' => 'required|numeric|min:1000',
            'loan_purpose' => 'required|string|max:1000',
            'business_description' => 'required|string|max:2000',
            'business_type' => 'required|string',
            'business_name' => 'required|string|max:255',
            'business_registration_number' => 'nullable|string|max:100',
            'business_start_date' => 'nullable|date',
            'number_of_employees' => 'nullable|integer|min:0',
            'monthly_revenue' => 'nullable|numeric|min:0',
            'monthly_expenses' => 'nullable|numeric|min:0',
            'applicant_name' => 'required|string|max:255',
            'applicant_email' => 'required|email|max:255',
            'applicant_phone' => 'required|string|max:50',
            'applicant_address' => 'required|string|max:1000',
            'applicant_id_number' => 'nullable|string|max:50',
            'applicant_dob' => 'nullable|date',
            'applicant_marital_status' => 'required|string',
            'applicant_dependents' => 'required|integer|min:0',
            'employment_status' => 'required|string',
            'employer_name' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'monthly_income' => 'nullable|numeric|min:0',
            'employment_years' => 'nullable|integer|min:0',
            'collateral_type' => 'required|string',
            'collateral_description' => 'nullable|string|max:1000',
            'collateral_value' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $application->update([
                'loan_product_id' => $request->loan_product_id,
                'requested_amount' => $request->requested_amount,
                'loan_purpose' => $request->loan_purpose,
                'business_description' => $request->business_description,
                'business_type' => $request->business_type,
                'business_name' => $request->business_name,
                'business_registration_number' => $request->business_registration_number,
                'business_start_date' => $request->business_start_date,
                'number_of_employees' => $request->number_of_employees,
                'monthly_revenue' => $request->monthly_revenue,
                'monthly_expenses' => $request->monthly_expenses,
                'applicant_name' => $request->applicant_name,
                'applicant_email' => $request->applicant_email,
                'applicant_phone' => $request->applicant_phone,
                'applicant_address' => $request->applicant_address,
                'applicant_id_number' => $request->applicant_id_number,
                'applicant_dob' => $request->applicant_dob,
                'applicant_marital_status' => $request->applicant_marital_status,
                'applicant_dependents' => $request->applicant_dependents,
                'employment_status' => $request->employment_status,
                'employer_name' => $request->employer_name,
                'job_title' => $request->job_title,
                'monthly_income' => $request->monthly_income,
                'employment_years' => $request->employment_years,
                'collateral_type' => $request->collateral_type,
                'collateral_description' => $request->collateral_description,
                'collateral_value' => $request->collateral_value,
                'updated_user_id' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->route('advanced_loan_management.applications.show', $id)
                ->with('success', 'Application updated successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Failed to update application: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Approve application
     */
    public function approveApplication(Request $request, $id)
    {
        $tenant = app('tenant');
        
        $application = AdvancedLoanApplication::where('tenant_id', $tenant->id)->findOrFail($id);

        if (!$application->canBeApproved()) {
            return response()->json(['success' => false, 'message' => 'Application cannot be approved in current status.']);
        }

        $validator = Validator::make($request->all(), [
            'approved_amount' => 'required|numeric|min:1000|max:' . $application->requested_amount,
            'review_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        DB::beginTransaction();

        try {
            $application->update([
                'status' => 'approved',
                'approved_amount' => $request->approved_amount,
                'review_notes' => $request->review_notes,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'updated_user_id' => auth()->id(),
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Application approved successfully.']);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Failed to approve application: ' . $e->getMessage()]);
        }
    }

    /**
     * Reject application
     */
    public function rejectApplication(Request $request, $id)
    {
        $tenant = app('tenant');
        
        $application = AdvancedLoanApplication::where('tenant_id', $tenant->id)->findOrFail($id);

        if (!$application->canBeRejected()) {
            return response()->json(['success' => false, 'message' => 'Application cannot be rejected in current status.']);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        DB::beginTransaction();

        try {
            $application->update([
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'updated_user_id' => auth()->id(),
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Application rejected successfully.']);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Failed to reject application: ' . $e->getMessage()]);
        }
    }

    /**
     * Disburse approved loan
     */
    public function disburseLoan(Request $request, $id)
    {
        $tenant = app('tenant');
        
        $application = AdvancedLoanApplication::where('tenant_id', $tenant->id)
            ->with(['loanProduct', 'applicant'])
            ->findOrFail($id);

        if ($application->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Only approved applications can be disbursed.']);
        }

        $validator = Validator::make($request->all(), [
            'disbursement_account_id' => 'required|exists:bank_accounts,id',
            'disbursement_date' => 'required|date',
            'loan_term_months' => 'required|integer|min:1|max:60',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()]);
        }

        DB::beginTransaction();

        try {
            // Create the loan record
            $loan = Loan::create([
                'tenant_id' => $tenant->id,
                'loan_id' => 'ADV-' . $application->application_number,
                'loan_product_id' => $application->loanProduct->id, // Map to existing loan product
                'borrower_id' => $application->applicant_id,
                'applied_amount' => $application->approved_amount,
                'total_payable' => $this->calculateTotalPayable($application->approved_amount, $application->loanProduct),
                'total_paid' => 0,
                'currency_id' => base_currency_id(),
                'first_payment_date' => Carbon::parse($request->disbursement_date)->addMonths(1),
                'release_date' => $request->disbursement_date,
                'late_payment_penalties' => $application->loanProduct->late_payment_fee,
                'description' => $application->loan_purpose,
                'status' => 2, // Active/Disbursed
                'approved_date' => now(),
                'approved_user_id' => auth()->id(),
                'created_user_id' => auth()->id(),
                'branch_id' => auth()->user()->branch_id,
                'debit_account_id' => $request->disbursement_account_id,
            ]);

            // Create bank transaction for disbursement
            $bankTransaction = BankTransaction::create([
                'tenant_id' => $tenant->id,
                'trans_date' => $request->disbursement_date,
                'bank_account_id' => $request->disbursement_account_id,
                'amount' => $application->approved_amount,
                'dr_cr' => 'dr',
                'type' => BankTransaction::TYPE_LOAN_DISBURSEMENT,
                'status' => BankTransaction::STATUS_APPROVED,
                'description' => 'Advanced Loan Disbursement - ' . $application->application_number,
                'created_user_id' => auth()->id(),
            ]);

            // Create transaction record
            $transaction = Transaction::create([
                'tenant_id' => $tenant->id,
                'trans_date' => $request->disbursement_date,
                'member_id' => $application->applicant_id,
                'loan_id' => $loan->id,
                'bank_account_id' => $request->disbursement_account_id,
                'amount' => $application->approved_amount,
                'dr_cr' => 'dr',
                'type' => 'Loan',
                'method' => 'Manual',
                'status' => 2,
                'note' => $application->loan_purpose,
                'description' => 'Advanced Loan Disbursement',
                'created_user_id' => auth()->id(),
                'branch_id' => auth()->user()->branch_id ?? null,
            ]);

            // Update application status
            $application->update([
                'status' => 'disbursed',
                'updated_user_id' => auth()->id(),
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Loan disbursed successfully.']);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Failed to disburse loan: ' . $e->getMessage()]);
        }
    }

    /**
     * Calculate total payable amount
     */
    private function calculateTotalPayable($amount, $loanProduct)
    {
        $interestAmount = ($amount * $loanProduct->interest_rate) / 100;
        return $amount + $interestAmount;
    }

    /**
     * Show form for creating loan terms from template
     */
    public function createLoanTermsFromTemplate(Request $request)
    {
        $tenant = app('tenant');
        
        // Check if Advanced Loan Management module is active
        if (!$tenant->advanced_loan_management_enabled) {
            return redirect()->route('dashboard.index')
                ->with('error', 'Advanced Loan Management module is not enabled for this tenant.');
        }
        
        $loanProducts = LoanProduct::where('tenant_id', $tenant->id)
            ->where('status', 1)
            ->get();
        
        // Get available legal templates
        $legalTemplates = \App\Models\LegalTemplate::active()
            ->orderBy('country_name')
            ->orderBy('template_name')
            ->get();
        
        // Get template ID from query parameter if provided
        $selectedTemplateId = $request->get('template_id');
        $selectedTemplate = null;
        
        if ($selectedTemplateId) {
            $selectedTemplate = \App\Models\LegalTemplate::find($selectedTemplateId);
        }
        
        return view('backend.admin.advanced_loan_management.loan_terms.create_from_template', 
            compact('loanProducts', 'legalTemplates', 'selectedTemplate'));
    }

    /**
     * Store loan terms created from template
     */
    public function storeLoanTermsFromTemplate(Request $request)
    {
        $tenant = app('tenant');
        
        // Check if Advanced Loan Management module is active
        if (!$tenant->advanced_loan_management_enabled) {
            return redirect()->route('dashboard.index')
                ->with('error', 'Advanced Loan Management module is not enabled for this tenant.');
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'loan_product_id' => 'nullable|exists:loan_products,id',
            'terms_and_conditions' => 'required|string',
            'privacy_policy' => 'required|string',
            'version' => 'required|string|max:50',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:effective_date',
            'is_default' => 'boolean',
            'template_id' => 'nullable|exists:legal_templates,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            // If this is set as default, remove default status from other terms
            if ($request->is_default) {
                LoanTermsAndPrivacy::where('tenant_id', $tenant->id)
                    ->where('loan_product_id', $request->loan_product_id)
                    ->update(['is_default' => false]);
            }

            $terms = new LoanTermsAndPrivacy();
            $terms->tenant_id = $tenant->id;
            $terms->loan_product_id = $request->loan_product_id;
            $terms->title = $request->title;
            $terms->terms_and_conditions = $request->terms_and_conditions;
            $terms->privacy_policy = $request->privacy_policy;
            $terms->version = $request->version;
            $terms->effective_date = $request->effective_date;
            $terms->expiry_date = $request->expiry_date;
            $terms->is_default = $request->boolean('is_default');
            $terms->is_active = true;
            $terms->created_by = auth()->id();
            $terms->save();

            DB::commit();

            return redirect()->route('loan_terms.create_from_template')
                ->with('success', 'Loan Terms and Privacy Policy created successfully from template');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Failed to create terms: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display legal templates management page
     */
    public function indexLegalTemplates()
    {
        $tenant = app('tenant');
        
        // Check if Advanced Loan Management module is active
        if (!$tenant->advanced_loan_management_enabled) {
            return redirect()->route('dashboard.index')
                ->with('error', 'Advanced Loan Management module is not enabled for this tenant.');
        }
        
        $templates = \App\Models\LegalTemplate::active()
            ->orderBy('country_name')
            ->orderBy('template_name')
            ->get();
        
        return view('backend.admin.advanced_loan_management.legal_templates.index', compact('templates'));
    }

    /**
     * Show form for editing legal template
     */
    public function editLegalTemplate($id)
    {
        $tenant = app('tenant');
        
        // Check if Advanced Loan Management module is active
        if (!$tenant->advanced_loan_management_enabled) {
            return redirect()->route('dashboard.index')
                ->with('error', 'Advanced Loan Management module is not enabled for this tenant.');
        }
        
        $template = \App\Models\LegalTemplate::findOrFail($id);
        
        return view('backend.admin.advanced_loan_management.legal_templates.edit', compact('template'));
    }

    /**
     * Update legal template
     */
    public function updateLegalTemplate(Request $request, $id)
    {
        $tenant = app('tenant');
        
        // Check if Advanced Loan Management module is active
        if (!$tenant->advanced_loan_management_enabled) {
            return redirect()->route('dashboard.index')
                ->with('error', 'Advanced Loan Management module is not enabled for this tenant.');
        }

        $template = \App\Models\LegalTemplate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'template_name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'terms_and_conditions' => 'required|string',
            'privacy_policy' => 'required|string',
            'version' => 'required|string|max:50',
            'applicable_laws' => 'nullable|array',
            'regulatory_bodies' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        DB::beginTransaction();

        try {
            $template->update([
                'template_name' => $request->template_name,
                'description' => $request->description,
                'terms_and_conditions' => $request->terms_and_conditions,
                'privacy_policy' => $request->privacy_policy,
                'version' => $request->version,
                'applicable_laws' => $request->applicable_laws ?? [],
                'regulatory_bodies' => $request->regulatory_bodies ?? [],
            ]);

            DB::commit();

            return redirect()->route('legal_templates.index')
                ->with('success', 'Legal template updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Failed to update template: ' . $e->getMessage())->withInput();
        }
    }
}
