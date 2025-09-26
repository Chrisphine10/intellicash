<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LoanTermsAndPrivacy;
use App\Models\Tenant;
use App\Models\User;

class LoanTermsAndPrivacySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->command->info('No tenants found. Skipping loan terms and privacy seeding.');
            return;
        }
        
        foreach ($tenants as $tenant) {
            // Get the first admin user for this tenant
            $adminUser = User::where('tenant_id', $tenant->id)
                ->where('user_type', 'admin')
                ->first();
            
            if (!$adminUser) {
                $this->command->warn("No admin user found for tenant {$tenant->id}. Skipping...");
                continue;
            }
            
            // Create default terms and privacy policy for this tenant
            LoanTermsAndPrivacy::create([
                'tenant_id' => $tenant->id,
                'loan_product_id' => null, // General terms
                'title' => 'Standard Loan Terms and Conditions',
                'terms_and_conditions' => $this->getDefaultTermsAndConditions(),
                'privacy_policy' => $this->getDefaultPrivacyPolicy(),
                'is_active' => true,
                'is_default' => true,
                'version' => '1.0',
                'effective_date' => now(),
                'created_by' => $adminUser->id,
            ]);
        }
    }
    
    private function getDefaultTermsAndConditions()
    {
        return '
        <h4>LOAN TERMS AND CONDITIONS</h4>
        <p><strong>Effective Date:</strong> ' . now()->format('F d, Y') . '</p>
        <p><strong>Governing Law:</strong> Laws of the Republic of Kenya</p>
        <p><strong>Regulatory Compliance:</strong> Central Bank of Kenya (CBK) Guidelines and Banking Act (Cap 488)</p>
        
        <h5>1. DEFINITIONS AND INTERPRETATION</h5>
        <p>In these Terms and Conditions, unless the context otherwise requires:</p>
        <ul>
            <li><strong>"Borrower"</strong> means the person(s) applying for and/or receiving the loan facility</li>
            <li><strong>"Lender"</strong> means the financial institution providing the loan facility</li>
            <li><strong>"Loan"</strong> means the principal amount advanced to the Borrower</li>
            <li><strong>"Interest"</strong> means the cost of borrowing as calculated in accordance with CBK guidelines</li>
            <li><strong>"Collateral"</strong> means any security provided for the loan facility</li>
            <li><strong>"Default"</strong> means failure to meet any obligation under this agreement</li>
        </ul>
        
        <h5>2. ELIGIBILITY AND APPLICATION REQUIREMENTS</h5>
        <ul>
            <li>You must be a Kenyan citizen or resident with valid identification</li>
            <li>Minimum age of 18 years as per the Constitution of Kenya</li>
            <li>Valid Kenya Revenue Authority (KRA) PIN certificate</li>
            <li>Proof of income and employment as per CBK requirements</li>
            <li>Bank account with a licensed financial institution in Kenya</li>
            <li>All information provided must be accurate and verifiable</li>
            <li>Consent to credit bureau checks as per the Credit Reference Bureau Act, 2013</li>
        </ul>
        
        <h5>3. LOAN APPROVAL AND DISBURSEMENT</h5>
        <ul>
            <li>Approval is subject to our credit assessment criteria and CBK guidelines</li>
            <li>We reserve the right to verify all information provided</li>
            <li>Loan disbursement is subject to execution of all required documentation</li>
            <li>We may require additional security or guarantors as per risk assessment</li>
            <li>Disbursement will be made to your designated bank account only</li>
        </ul>
        
        <h5>4. INTEREST RATES AND CHARGES</h5>
        <ul>
            <li>Interest rates are calculated as per CBK guidelines and Banking Act</li>
            <li>All interest rates are clearly disclosed before loan disbursement</li>
            <li>Interest is calculated on a reducing balance basis unless otherwise specified</li>
            <li>Additional charges may include: processing fees, insurance, late payment penalties</li>
            <li>All charges are disclosed in accordance with CBK consumer protection guidelines</li>
            <li>Early repayment may attract prepayment charges as per CBK guidelines</li>
        </ul>
        
        <h5>5. REPAYMENT TERMS</h5>
        <ul>
            <li>Repayment schedule is clearly defined in your loan agreement</li>
            <li>Payments must be made on or before the due date</li>
            <li>Late payments attract penalties as per CBK guidelines (maximum 1% per month)</li>
            <li>We accept payments through various channels as per CBK guidelines</li>
            <li>You may request restructuring subject to our approval and CBK guidelines</li>
        </ul>
        
        <h5>6. COLLATERAL AND SECURITY</h5>
        <ul>
            <li>Collateral requirements are based on loan amount and risk assessment</li>
            <li>All collateral must be properly registered and insured</li>
            <li>We have the right to perfect security interests as per the Movable Property Security Rights Act, 2017</li>
            <li>Collateral valuation must be done by CBK-approved valuers</li>
            <li>We may require additional security during the loan term</li>
        </ul>
        
        <h5>7. DEFAULT AND REMEDIES</h5>
        <ul>
            <li>Default occurs if payments are more than 30 days overdue</li>
            <li>We will follow CBK guidelines for default management</li>
            <li>We may report defaults to credit reference bureaus as per the Credit Reference Bureau Act</li>
            <li>We reserve the right to enforce security and recover outstanding amounts</li>
            <li>Legal action may be taken in accordance with Kenyan law</li>
            <li>Collection costs may be added to outstanding balance as per CBK guidelines</li>
        </ul>
        
        <h5>8. DATA PROTECTION AND PRIVACY</h5>
        <ul>
            <li>We comply with the Data Protection Act, 2019</li>
            <li>Your personal data is processed in accordance with Kenyan data protection laws</li>
            <li>We may share information with credit reference bureaus as per the Credit Reference Bureau Act</li>
            <li>You have rights under the Data Protection Act, 2019</li>
            <li>We maintain appropriate security measures to protect your data</li>
        </ul>
        
        <h5>9. DISPUTE RESOLUTION</h5>
        <ul>
            <li>Disputes will be resolved through negotiation and mediation</li>
            <li>If mediation fails, disputes will be resolved through arbitration under the Arbitration Act</li>
            <li>Arbitration will be conducted in Nairobi, Kenya</li>
            <li>You may also refer disputes to the Banking Ombudsman</li>
            <li>Kenyan courts have jurisdiction over any legal proceedings</li>
        </ul>
        
        <h5>10. REGULATORY COMPLIANCE</h5>
        <ul>
            <li>We are licensed and regulated by the Central Bank of Kenya</li>
            <li>We comply with all applicable Kenyan laws and regulations</li>
            <li>We follow CBK guidelines on consumer protection</li>
            <li>We are subject to CBK supervision and examination</li>
            <li>Terms may be modified to comply with regulatory changes</li>
        </ul>
        
        <h5>11. FORCE MAJEURE</h5>
        <ul>
            <li>We are not liable for delays due to circumstances beyond our control</li>
            <li>This includes natural disasters, government actions, or regulatory changes</li>
            <li>We will notify you of any material changes affecting your loan</li>
        </ul>
        
        <h5>12. CONTACT INFORMATION</h5>
        <p>For questions about these terms or to file a complaint:</p>
        <ul>
            <li>Customer Service: Available during business hours</li>
            <li>Email: customer.service@company.co.ke</li>
            <li>Phone: +254 XXX XXX XXX</li>
            <li>Address: [Company Address], Nairobi, Kenya</li>
            <li>Banking Ombudsman: For unresolved disputes</li>
        </ul>
        
        <p><strong>By accepting these terms, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions in accordance with Kenyan law.</strong></p>
        ';
    }
    
    private function getDefaultPrivacyPolicy()
    {
        return '
        <h4>PRIVACY POLICY</h4>
        <p><strong>Effective Date:</strong> ' . now()->format('F d, Y') . '</p>
        <p><strong>Governing Law:</strong> Data Protection Act, 2019 (Kenya)</p>
        <p><strong>Regulatory Compliance:</strong> Office of the Data Protection Commissioner (ODPC)</p>
        
        <h5>1. DATA CONTROLLER INFORMATION</h5>
        <p>We are the data controller for your personal information. Our contact details are:</p>
        <ul>
            <li><strong>Company:</strong> [Company Name]</li>
            <li><strong>Address:</strong> [Company Address], Nairobi, Kenya</li>
            <li><strong>Data Protection Officer:</strong> dpo@company.co.ke</li>
            <li><strong>Phone:</strong> +254 XXX XXX XXX</li>
            <li><strong>Registration:</strong> Licensed by Central Bank of Kenya</li>
        </ul>
        
        <h5>2. PERSONAL INFORMATION WE COLLECT</h5>
        <p>We collect and process the following categories of personal data:</p>
        <ul>
            <li><strong>Identity Data:</strong> Full name, national ID number, passport details, KRA PIN</li>
            <li><strong>Contact Data:</strong> Physical address, postal address, email, phone numbers</li>
            <li><strong>Financial Data:</strong> Income, employment details, bank statements, credit history</li>
            <li><strong>Biometric Data:</strong> Fingerprints, photographs (where required by law)</li>
            <li><strong>Transaction Data:</strong> Loan history, payment records, account balances</li>
            <li><strong>Technical Data:</strong> IP address, device information, website usage</li>
            <li><strong>Marketing Data:</strong> Communication preferences, marketing consent</li>
        </ul>
        
        <h5>3. LEGAL BASIS FOR PROCESSING</h5>
        <p>We process your personal data based on the following legal grounds under the Data Protection Act, 2019:</p>
        <ul>
            <li><strong>Consent:</strong> Where you have given clear consent for specific processing</li>
            <li><strong>Contract Performance:</strong> To perform our contract with you</li>
            <li><strong>Legal Obligation:</strong> To comply with legal requirements (Banking Act, CBK guidelines)</li>
            <li><strong>Legitimate Interest:</strong> For our legitimate business interests (credit assessment, fraud prevention)</li>
            <li><strong>Vital Interest:</strong> To protect your vital interests or those of another person</li>
        </ul>
        
        <h5>4. HOW WE USE YOUR INFORMATION</h5>
        <ul>
            <li><strong>Loan Processing:</strong> To assess, approve, and manage your loan application</li>
            <li><strong>Credit Assessment:</strong> To evaluate your creditworthiness and repayment capacity</li>
            <li><strong>Regulatory Compliance:</strong> To comply with CBK, KRA, and other regulatory requirements</li>
            <li><strong>Risk Management:</strong> To assess and manage credit and operational risks</li>
            <li><strong>Customer Service:</strong> To provide support and communicate with you</li>
            <li><strong>Marketing:</strong> To send you relevant products and services (with your consent)</li>
            <li><strong>Legal Requirements:</strong> To comply with court orders, legal processes, and investigations</li>
        </ul>
        
        <h5>5. INFORMATION SHARING AND DISCLOSURE</h5>
        <p>We may share your personal data with:</p>
        <ul>
            <li><strong>Credit Reference Bureaus:</strong> As required by the Credit Reference Bureau Act, 2013</li>
            <li><strong>Regulatory Authorities:</strong> CBK, KRA, ODPC, and other relevant authorities</li>
            <li><strong>Service Providers:</strong> Third parties who assist us in providing services</li>
            <li><strong>Legal Requirements:</strong> When required by law or court order</li>
            <li><strong>Business Transfers:</strong> In case of merger, acquisition, or asset sale</li>
            <li><strong>Consent:</strong> With your explicit consent for specific purposes</li>
        </ul>
        <p><strong>We do not sell your personal data to third parties.</strong></p>
        
        <h5>6. DATA SECURITY MEASURES</h5>
        <p>We implement appropriate technical and organizational measures to protect your data:</p>
        <ul>
            <li><strong>Encryption:</strong> Data is encrypted in transit and at rest</li>
            <li><strong>Access Controls:</strong> Strict access controls and authentication</li>
            <li><strong>Regular Audits:</strong> Regular security assessments and audits</li>
            <li><strong>Staff Training:</strong> Regular data protection training for staff</li>
            <li><strong>Incident Response:</strong> Procedures for handling data breaches</li>
            <li><strong>Physical Security:</strong> Secure facilities and equipment</li>
        </ul>
        
        <h5>7. DATA RETENTION</h5>
        <p>We retain your personal data for the following periods:</p>
        <ul>
            <li><strong>Active Loans:</strong> Duration of loan plus 7 years (as per Banking Act)</li>
            <li><strong>Credit Information:</strong> 5 years after loan closure (Credit Reference Bureau Act)</li>
            <li><strong>Regulatory Records:</strong> As required by CBK and other regulators</li>
            <li><strong>Marketing Data:</strong> Until you withdraw consent or 3 years of inactivity</li>
            <li><strong>Legal Requirements:</strong> As required by applicable laws</li>
        </ul>
        
        <h5>8. YOUR RIGHTS UNDER THE DATA PROTECTION ACT, 2019</h5>
        <p>You have the following rights regarding your personal data:</p>
        <ul>
            <li><strong>Right of Access:</strong> Request copies of your personal data</li>
            <li><strong>Right of Rectification:</strong> Correct inaccurate or incomplete data</li>
            <li><strong>Right of Erasure:</strong> Request deletion of your data (subject to legal requirements)</li>
            <li><strong>Right to Restrict Processing:</strong> Limit how we use your data</li>
            <li><strong>Right to Data Portability:</strong> Receive your data in a structured format</li>
            <li><strong>Right to Object:</strong> Object to processing based on legitimate interests</li>
            <li><strong>Right to Withdraw Consent:</strong> Withdraw consent for consent-based processing</li>
            <li><strong>Right to Complain:</strong> Lodge complaints with the ODPC</li>
        </ul>
        
        <h5>9. COOKIES AND TRACKING</h5>
        <ul>
            <li>We use cookies and similar technologies on our website</li>
            <li>Cookies help us provide better services and user experience</li>
            <li>You can control cookie settings through your browser</li>
            <li>Some cookies are essential for website functionality</li>
        </ul>
        
        <h5>10. INTERNATIONAL DATA TRANSFERS</h5>
        <ul>
            <li>We may transfer your data to countries outside Kenya</li>
            <li>Such transfers comply with the Data Protection Act, 2019</li>
            <li>We ensure adequate protection through appropriate safeguards</li>
            <li>We obtain your consent where required by law</li>
        </ul>
        
        <h5>11. CHILDREN\'S PRIVACY</h5>
        <ul>
            <li>We do not knowingly collect data from children under 18</li>
            <li>If we discover we have collected data from a child, we will delete it</li>
            <li>Parents can contact us to review or delete their child\'s data</li>
        </ul>
        
        <h5>12. DATA BREACH NOTIFICATION</h5>
        <ul>
            <li>We will notify the ODPC within 72 hours of discovering a breach</li>
            <li>We will notify affected individuals without undue delay</li>
            <li>We will take immediate steps to contain and remedy the breach</li>
        </ul>
        
        <h5>13. CHANGES TO THIS PRIVACY POLICY</h5>
        <ul>
            <li>We may update this policy from time to time</li>
            <li>We will notify you of material changes</li>
            <li>Continued use of our services constitutes acceptance of changes</li>
            <li>We will maintain previous versions for your reference</li>
        </ul>
        
        <h5>14. CONTACT INFORMATION</h5>
        <p>For questions about this privacy policy or to exercise your rights:</p>
        <ul>
            <li><strong>Data Protection Officer:</strong> dpo@company.co.ke</li>
            <li><strong>Phone:</strong> +254 XXX XXX XXX</li>
            <li><strong>Address:</strong> [Company Address], Nairobi, Kenya</li>
            <li><strong>Office of the Data Protection Commissioner:</strong> For complaints</li>
            <li><strong>Website:</strong> www.odpc.go.ke</li>
        </ul>
        
        <p><strong>By using our services, you acknowledge that you have read and understood this Privacy Policy and agree to the processing of your personal data as described herein.</strong></p>
        ';
    }
}
