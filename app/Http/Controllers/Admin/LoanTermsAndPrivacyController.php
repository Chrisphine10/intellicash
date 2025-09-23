<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanTermsAndPrivacy;
use App\Models\LoanProduct;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LoanTermsAndPrivacyController extends Controller
{
    /**
     * Get terms and privacy policy for a specific loan product.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTermsForProduct(Request $request): JsonResponse
    {
        $productId = $request->query('product_id');
        
        if (!$productId) {
            return response()->json([
                'success' => false,
                'message' => 'Product ID is required'
            ], 400);
        }

        // Verify the loan product exists
        $loanProduct = LoanProduct::find($productId);
        if (!$loanProduct) {
            return response()->json([
                'success' => false,
                'message' => 'Loan product not found'
            ], 404);
        }

        // Since the loan_terms_and_privacy table was dropped, return default terms
        $defaultTerms = $this->getDefaultTermsForProduct($loanProduct);

        return response()->json([
            'success' => true,
            'terms' => $defaultTerms
        ]);
    }

    /**
     * Get default terms and privacy policy for a loan product.
     *
     * @param LoanProduct $loanProduct
     * @return array
     */
    private function getDefaultTermsForProduct(LoanProduct $loanProduct): array
    {
        $termsAndConditions = '<h3>Loan Terms and Conditions</h3>
        <p>Please read these terms and conditions carefully before applying for a loan.</p>
        
        <h4>Loan Details</h4>
        <ul>
            <li><strong>Product Name:</strong> ' . $loanProduct->name . '</li>
            <li><strong>Interest Rate:</strong> ' . $loanProduct->interest_rate . '% per annum</li>
            <li><strong>Interest Type:</strong> ' . ucwords(str_replace('_', ' ', $loanProduct->interest_type)) . '</li>
            <li><strong>Minimum Amount:</strong> $' . number_format($loanProduct->minimum_amount, 2) . '</li>
            <li><strong>Maximum Amount:</strong> $' . number_format($loanProduct->maximum_amount, 2) . '</li>
            <li><strong>Term:</strong> ' . $loanProduct->term . ' ' . $loanProduct->term_period . '</li>
            <li><strong>Late Payment Penalties:</strong> $' . number_format($loanProduct->late_payment_penalties, 2) . '</li>
        </ul>

        <h4>Application Fees</h4>
        <ul>
            <li><strong>Application Fee:</strong> $' . number_format($loanProduct->loan_application_fee, 2) . ' (' . ($loanProduct->loan_application_fee_type ? 'Percentage' : 'Fixed') . ')</li>
            <li><strong>Processing Fee:</strong> $' . number_format($loanProduct->loan_processing_fee, 2) . ' (' . ($loanProduct->loan_processing_fee_type ? 'Percentage' : 'Fixed') . ')</li>
        </ul>

        <h4>General Terms</h4>
        <ul>
            <li>All loan applications are subject to approval</li>
            <li>Interest will be calculated based on the selected interest type</li>
            <li>Late payments will incur additional penalties</li>
            <li>Early repayment may be subject to fees</li>
            <li>All terms are subject to change without notice</li>
        </ul>';

        $privacyPolicy = '<h3>Privacy Policy</h3>
        <p>We are committed to protecting your privacy and personal information. This policy explains how we collect, use, and protect your data.</p>
        
        <h4>Information We Collect</h4>
        <ul>
            <li>Personal identification information (name, address, phone number, email)</li>
            <li>Financial information (income, employment details, bank statements)</li>
            <li>Credit history and references</li>
            <li>Documentation required for loan processing</li>
        </ul>

        <h4>How We Use Your Information</h4>
        <ul>
            <li>To process your loan application</li>
            <li>To verify your identity and financial status</li>
            <li>To communicate with you about your loan</li>
            <li>To comply with legal and regulatory requirements</li>
        </ul>

        <h4>Data Protection</h4>
        <ul>
            <li>We implement security measures to protect your personal information</li>
            <li>We do not sell your personal information to third parties</li>
            <li>We may share information with credit bureaus and regulatory authorities as required</li>
            <li>You have the right to access and correct your personal information</li>
        </ul>';

        return [
            'id' => 'default_' . $loanProduct->id,
            'title' => $loanProduct->name . ' - Terms and Conditions',
            'terms_and_conditions' => $termsAndConditions,
            'privacy_policy' => $privacyPolicy,
            'version' => '1.0',
            'effective_date' => now()->format('Y-m-d'),
            'is_product_specific' => true
        ];
    }

    /**
     * Get template details for creating new terms.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTemplateDetails(Request $request): JsonResponse
    {
        $templateId = $request->query('template_id');
        
        if (!$templateId) {
            return response()->json([
                'success' => false,
                'message' => 'Template ID is required'
            ], 400);
        }

        $template = LoanTermsAndPrivacy::find($templateId);
        
        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'template' => [
                'id' => $template->id,
                'title' => $template->title,
                'terms_and_conditions' => $template->terms_and_conditions,
                'privacy_policy' => $template->privacy_policy,
                'version' => $template->version
            ]
        ]);
    }

    /**
     * Get available countries for terms templates.
     *
     * @return JsonResponse
     */
    public function getAvailableCountries(): JsonResponse
    {
        // This could be expanded to include actual country data
        $countries = [
            ['id' => 1, 'name' => 'United States', 'code' => 'US'],
            ['id' => 2, 'name' => 'United Kingdom', 'code' => 'UK'],
            ['id' => 3, 'name' => 'Canada', 'code' => 'CA'],
            ['id' => 4, 'name' => 'Australia', 'code' => 'AU'],
            // Add more countries as needed
        ];

        return response()->json([
            'success' => true,
            'countries' => $countries
        ]);
    }

    /**
     * Get template types for a specific country.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTemplateTypesForCountry(Request $request): JsonResponse
    {
        $countryId = $request->query('country_id');
        
        if (!$countryId) {
            return response()->json([
                'success' => false,
                'message' => 'Country ID is required'
            ], 400);
        }

        // This could be expanded to include actual template types based on country
        $templateTypes = [
            ['id' => 1, 'name' => 'Standard Personal Loan', 'description' => 'Basic personal loan terms'],
            ['id' => 2, 'name' => 'Business Loan', 'description' => 'Terms for business loans'],
            ['id' => 3, 'name' => 'Mortgage Loan', 'description' => 'Terms for mortgage loans'],
            ['id' => 4, 'name' => 'Auto Loan', 'description' => 'Terms for auto loans'],
        ];

        return response()->json([
            'success' => true,
            'template_types' => $templateTypes
        ]);
    }
}
