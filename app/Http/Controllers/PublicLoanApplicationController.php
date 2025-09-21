<?php

namespace App\Http\Controllers;

use App\Models\AdvancedLoanApplication;
use App\Models\LoanProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PublicLoanApplicationController extends Controller
{
    /**
     * Show the loan application form
     */
    public function showApplicationForm(Request $request)
    {
        $tenant = app('tenant');
        
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect()->route('tenant.login', ['tenant' => $tenant->slug])
                ->with('error', 'Please log in to access the loan application.');
        }
        
        // Get active loan products
        $loanProducts = LoanProduct::where('tenant_id', $tenant->id)
            ->active()
            ->orderBy('name')
            ->get();

        return view('frontend.loan_application.form', compact('loanProducts'));
    }

    /**
     * Store the loan application
     */
    public function storeApplication(Request $request)
    {
        $tenant = app('tenant');
        
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect()->route('tenant.login', ['tenant' => $tenant->slug])
                ->with('error', 'Please log in to submit a loan application.');
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
            'guarantor_name' => 'nullable|string|max:255',
            'guarantor_phone' => 'nullable|string|max:50',
            'guarantor_email' => 'nullable|email|max:255',
            'guarantor_relationship' => 'nullable|string|max:100',
            'guarantor_income' => 'nullable|numeric|min:0',
            'business_documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'financial_documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'personal_documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'collateral_documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Get the loan product to validate requirements
        $loanProduct = LoanProduct::where('tenant_id', $tenant->id)
            ->findOrFail($request->loan_product_id);

        // Validate loan amount
        if (!$loanProduct->isLoanAmountValid($request->requested_amount)) {
            return back()->with('error', 'Loan amount must be between KES ' . number_format($loanProduct->minimum_amount, 0) . ' and KES ' . number_format($loanProduct->maximum_amount, 0))
                ->withInput();
        }

        // Validate applicant age
        if ($request->applicant_dob) {
            $age = Carbon::parse($request->applicant_dob)->age;
            if (!$loanProduct->isApplicantAgeValid($age)) {
                return back()->with('error', 'Applicant age must be between ' . $loanProduct->minimum_age . ' and ' . $loanProduct->maximum_age . ' years')
                    ->withInput();
            }
        }

        // Validate monthly income
        if ($request->monthly_income && !$loanProduct->isMonthlyIncomeValid($request->monthly_income)) {
            return back()->with('error', 'Monthly income must be at least KES ' . number_format($loanProduct->minimum_monthly_income, 0))
                ->withInput();
        }

        // Validate business age
        if ($request->business_start_date) {
            $businessMonths = Carbon::parse($request->business_start_date)->diffInMonths(now());
            if (!$loanProduct->isBusinessAgeValid($businessMonths)) {
                return back()->with('error', 'Business must be at least ' . $loanProduct->minimum_business_months . ' months old')
                    ->withInput();
            }
        }

        // Validate collateral type
        if (!$loanProduct->isCollateralTypeAccepted($request->collateral_type)) {
            return back()->with('error', 'This loan product does not accept ' . $request->collateral_type . ' as collateral')
                ->withInput();
        }

        // Validate collateral value
        if ($request->collateral_value && !$loanProduct->isCollateralValueValid($request->collateral_value, $request->requested_amount)) {
            return back()->with('error', 'Collateral value does not meet requirements')
                ->withInput();
        }

        DB::beginTransaction();

        try {
            // Handle file uploads
            $businessDocuments = $this->handleFileUploads($request, 'business_documents', 'loan_applications/business');
            $financialDocuments = $this->handleFileUploads($request, 'financial_documents', 'loan_applications/financial');
            $personalDocuments = $this->handleFileUploads($request, 'personal_documents', 'loan_applications/personal');
            $collateralDocuments = $this->handleFileUploads($request, 'collateral_documents', 'loan_applications/collateral');

            // Prepare guarantor details
            $guarantorDetails = null;
            if ($request->guarantor_name) {
                $guarantorDetails = [
                    'name' => $request->guarantor_name,
                    'phone' => $request->guarantor_phone,
                    'email' => $request->guarantor_email,
                    'relationship' => $request->guarantor_relationship,
                    'income' => $request->guarantor_income,
                ];
            }

            // Create the application
            $application = AdvancedLoanApplication::create([
                'tenant_id' => $tenant->id,
                'loan_product_id' => $request->loan_product_id,
                'applicant_id' => 1, // Placeholder - will be updated when member is created
                'application_type' => $loanProduct->product_type,
                'application_date' => now(),
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
                'collateral_documents' => $collateralDocuments,
                'guarantor_details' => $guarantorDetails,
                'business_documents' => $businessDocuments,
                'financial_documents' => $financialDocuments,
                'personal_documents' => $personalDocuments,
                'status' => 'submitted',
                'risk_level' => 'medium', // Default risk level
                'created_user_id' => null, // Public application
            ]);

            DB::commit();

            return redirect()->route('loan_application.success', $application->id)
                ->with('success', 'Loan application submitted successfully! Application Number: ' . $application->application_number);

        } catch (\Exception $e) {
            DB::rollback();
            
            // Clean up uploaded files on error
            $this->cleanupUploadedFiles([
                $businessDocuments ?? [],
                $financialDocuments ?? [],
                $personalDocuments ?? [],
                $collateralDocuments ?? []
            ]);

            return back()->with('error', 'Failed to submit application: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show application success page
     */
    public function showSuccess($id)
    {
        $tenant = app('tenant');
        
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect()->route('tenant.login', ['tenant' => $tenant->slug])
                ->with('error', 'Please log in to view application details.');
        }
        
        $application = AdvancedLoanApplication::where('tenant_id', $tenant->id)
            ->with('loanProduct')
            ->findOrFail($id);

        return view('frontend.loan_application.success', compact('application'));
    }

    /**
     * Show application status
     */
    public function showStatus(Request $request)
    {
        $tenant = app('tenant');
        
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect()->route('tenant.login', ['tenant' => $tenant->slug])
                ->with('error', 'Please log in to check application status.');
        }
        
        $application = null;
        
        if ($request->filled('application_number')) {
            $application = AdvancedLoanApplication::where('tenant_id', $tenant->id)
                ->where('application_number', $request->application_number)
                ->with('loanProduct')
                ->first();
        }

        return view('frontend.loan_application.status', compact('application'));
    }

    /**
     * Handle file uploads
     */
    private function handleFileUploads(Request $request, $fieldName, $directory)
    {
        if (!$request->hasFile($fieldName)) {
            return null;
        }

        $files = [];
        $uploadedFiles = $request->file($fieldName);

        // Ensure it's always an array
        if (!is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }

        foreach ($uploadedFiles as $file) {
            if ($file && $file->isValid()) {
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs($directory, $filename, 'public');
                $files[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'filename' => $filename,
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ];
            }
        }

        return !empty($files) ? $files : null;
    }

    /**
     * Clean up uploaded files
     */
    private function cleanupUploadedFiles(array $fileArrays)
    {
        foreach ($fileArrays as $files) {
            if ($files) {
                foreach ($files as $file) {
                    if (isset($file['path']) && Storage::disk('public')->exists($file['path'])) {
                        Storage::disk('public')->delete($file['path']);
                    }
                }
            }
        }
    }

    /**
     * Get loan products for AJAX
     */
    public function getLoanProducts(Request $request)
    {
        $tenant = app('tenant');
        
        $loanProducts = LoanProduct::where('tenant_id', $tenant->id)
            ->active()
            ->orderBy('name')
            ->get();

        return response()->json($loanProducts);
    }

    /**
     * Get loan product details for AJAX
     */
    public function getLoanProductDetails(Request $request, $id)
    {
        $tenant = app('tenant');
        
        $loanProduct = LoanProduct::where('tenant_id', $tenant->id)
            ->findOrFail($id);

        return response()->json([
            'product' => $loanProduct,
            'formatted_amount_range' => $loanProduct->formatted_amount_range,
            'formatted_terms' => $loanProduct->formatted_terms,
            'processing_fee' => $loanProduct->processing_fee,
            'processing_fee_type' => $loanProduct->processing_fee_type_label,
            'application_fee' => $loanProduct->application_fee,
            'application_fee_type' => $loanProduct->application_fee_type_label,
            'requires_collateral' => $loanProduct->requires_collateral,
            'requires_guarantor' => $loanProduct->requires_guarantor,
            'accepted_collateral_types' => $loanProduct->accepted_collateral_types,
            'requires_business_plan' => $loanProduct->requires_business_plan,
            'requires_financial_statements' => $loanProduct->requires_financial_statements,
            'requires_bank_statements' => $loanProduct->requires_bank_statements,
            'bank_statement_months' => $loanProduct->bank_statement_months,
            'requires_tax_certificates' => $loanProduct->requires_tax_certificates,
            'requires_business_registration' => $loanProduct->requires_business_registration,
        ]);
    }
}
