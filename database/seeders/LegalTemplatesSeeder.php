<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LegalTemplate;

class LegalTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Kenya Templates
        LegalTemplate::firstOrCreate(
            [
                'country_code' => 'KEN',
                'template_name' => 'Default General Commercial Loan',
                'template_type' => 'general',
            ],
            [
                'country_name' => 'Kenya',
                'version' => '1.0',
                'terms_and_conditions' => $this->getKenyaTerms(),
                'privacy_policy' => $this->getKenyaPrivacy(),
                'description' => 'Default comprehensive terms for general commercial loans in Kenya',
                'applicable_laws' => ['Banking Act (Cap 488)', 'Data Protection Act, 2019', 'Credit Reference Bureau Act, 2013'],
                'regulatory_bodies' => ['Central Bank of Kenya (CBK)', 'Office of the Data Protection Commissioner (ODPC)'],
                'is_active' => true,
                'is_system_template' => true,
                'language_code' => 'en'
            ]
        );

        // Uganda Templates
        LegalTemplate::firstOrCreate(
            [
                'country_code' => 'UGA',
                'template_name' => 'Default General Commercial Loan',
                'template_type' => 'general',
            ],
            [
                'country_name' => 'Uganda',
                'version' => '1.0',
                'terms_and_conditions' => $this->getUgandaTerms(),
                'privacy_policy' => $this->getUgandaPrivacy(),
                'description' => 'Default comprehensive terms for general commercial loans in Uganda',
                'applicable_laws' => ['Financial Institutions Act, 2004', 'Data Protection and Privacy Act, 2019'],
                'regulatory_bodies' => ['Bank of Uganda (BOU)', 'Personal Data Protection Office (PDPO)'],
                'is_active' => true,
                'is_system_template' => true,
                'language_code' => 'en'
            ]
        );

        // Tanzania Templates
        LegalTemplate::firstOrCreate(
            [
                'country_code' => 'TZA',
                'template_name' => 'Default General Commercial Loan',
                'template_type' => 'general',
            ],
            [
                'country_name' => 'Tanzania',
                'version' => '1.0',
                'terms_and_conditions' => $this->getTanzaniaTerms(),
                'privacy_policy' => $this->getTanzaniaPrivacy(),
                'description' => 'Default comprehensive terms for general commercial loans in Tanzania',
                'applicable_laws' => ['Banking and Financial Institutions Act, 2006', 'Personal Data Protection Act, 2022'],
                'regulatory_bodies' => ['Bank of Tanzania (BOT)', 'Personal Data Protection Commission (PDPC)'],
                'is_active' => true,
                'is_system_template' => true,
                'language_code' => 'en'
            ]
        );
    }

    private function getKenyaTerms()
    {
        return '
        <h2>LOAN TERMS AND CONDITIONS - KENYA</h2>
        <p><strong>Effective Date:</strong> [DATE]</p>
        <p><strong>Governing Law:</strong> Laws of the Republic of Kenya</p>
        <p><strong>Regulatory Compliance:</strong> Central Bank of Kenya (CBK) Guidelines</p>
        
        <h3>1. ELIGIBILITY REQUIREMENTS</h3>
        <p>To be eligible for this loan, you must meet the following criteria:</p>
        <ul>
            <li>Kenyan citizen or resident with valid identification (National ID or Passport)</li>
            <li>Minimum age of 18 years as per the Constitution of Kenya</li>
            <li>Valid Kenya Revenue Authority (KRA) PIN certificate</li>
            <li>Proof of income and employment as per CBK requirements</li>
            <li>Bank account with a licensed financial institution in Kenya</li>
            <li>Clean credit history with credit reference bureaus</li>
            <li>Minimum monthly income of KES 15,000</li>
        </ul>
        
        <h3>2. LOAN TERMS</h3>
        <ul>
            <li><strong>Loan Amount:</strong> Minimum KES 5,000 - Maximum KES 500,000</li>
            <li><strong>Repayment Period:</strong> 1-24 months</li>
            <li><strong>Interest Rate:</strong> 2.5% per month (30% per annum)</li>
            <li><strong>Processing Fee:</strong> 3% of loan amount (minimum KES 500)</li>
            <li><strong>Late Payment Fee:</strong> KES 500 per occurrence</li>
        </ul>
        
        <h3>3. INTEREST RATES AND CHARGES</h3>
        <ul>
            <li>Interest rates calculated as per CBK guidelines and Banking Act</li>
            <li>Late payment penalties as per CBK guidelines (maximum 1% per month)</li>
            <li>All charges disclosed in accordance with CBK consumer protection guidelines</li>
            <li>Early repayment is allowed without penalty</li>
        </ul>
        
        <h3>4. REPAYMENT TERMS</h3>
        <ul>
            <li>Monthly installments due on the same date each month</li>
            <li>Automatic deduction from your bank account preferred</li>
            <li>Manual payment accepted at our offices or via mobile money</li>
            <li>Default occurs after 30 days of non-payment</li>
        </ul>
        
        <h3>5. DATA PROTECTION</h3>
        <ul>
            <li>We comply with the Data Protection Act, 2019</li>
            <li>Your personal data is processed in accordance with Kenyan data protection laws</li>
            <li>We may share information with credit reference bureaus as per the Credit Reference Bureau Act, 2013</li>
            <li>Your data will be used for loan assessment, collection, and regulatory reporting</li>
        </ul>
        
        <h3>6. DEFAULT AND COLLECTION</h3>
        <ul>
            <li>Default interest rate: 5% per month on outstanding amount</li>
            <li>Collection costs will be added to outstanding balance</li>
            <li>Legal action may be taken for amounts over KES 100,000</li>
            <li>Credit bureau reporting for defaults over 90 days</li>
        </ul>
        
        <h3>7. CUSTOMER RIGHTS</h3>
        <ul>
            <li>Right to receive clear loan terms before signing</li>
            <li>Right to early repayment without penalty</li>
            <li>Right to dispute charges within 30 days</li>
            <li>Right to data protection and privacy</li>
        </ul>
        ';
    }

    private function getKenyaPrivacy()
    {
        return '
        <h2>PRIVACY POLICY - KENYA</h2>
        <p><strong>Effective Date:</strong> [DATE]</p>
        <p><strong>Governing Law:</strong> Data Protection Act, 2019 (Kenya)</p>
        <p><strong>Regulatory Compliance:</strong> Office of the Data Protection Commissioner (ODPC)</p>
        
        <h3>1. INFORMATION WE COLLECT</h3>
        <p>We collect the following types of personal information:</p>
        <ul>
            <li><strong>Personal Identification:</strong> Name, National ID, passport details, date of birth</li>
            <li><strong>Contact Information:</strong> Phone number, email address, physical address</li>
            <li><strong>Financial Information:</strong> Bank account details, income statements, employment details</li>
            <li><strong>Credit Information:</strong> Credit history, existing loans, payment behavior</li>
            <li><strong>Device Information:</strong> IP address, browser type, device identifiers</li>
        </ul>
        
        <h3>2. HOW WE USE YOUR INFORMATION</h3>
        <ul>
            <li>To assess your loan application and determine eligibility</li>
            <li>To process loan disbursements and collections</li>
            <li>To comply with regulatory requirements and reporting</li>
            <li>To communicate with you about your loan account</li>
            <li>To prevent fraud and ensure security</li>
            <li>To improve our services and develop new products</li>
        </ul>
        
        <h3>3. INFORMATION SHARING</h3>
        <p>We may share your information with:</p>
        <ul>
            <li><strong>Credit Reference Bureaus:</strong> As required by the Credit Reference Bureau Act, 2013</li>
            <li><strong>Regulatory Authorities:</strong> Central Bank of Kenya and other relevant authorities</li>
            <li><strong>Service Providers:</strong> Third parties who help us provide our services</li>
            <li><strong>Legal Requirements:</strong> When required by law or court order</li>
        </ul>
        
        <h3>4. YOUR RIGHTS UNDER DATA PROTECTION ACT, 2019</h3>
        <ul>
            <li><strong>Right of Access:</strong> Request copies of your personal data</li>
            <li><strong>Right of Rectification:</strong> Correct inaccurate or incomplete data</li>
            <li><strong>Right of Erasure:</strong> Request deletion of your personal data</li>
            <li><strong>Right to Restrict Processing:</strong> Limit how we use your data</li>
            <li><strong>Right to Data Portability:</strong> Receive your data in a structured format</li>
            <li><strong>Right to Object:</strong> Object to processing of your personal data</li>
        </ul>
        
        <h3>5. DATA SECURITY</h3>
        <ul>
            <li>We implement appropriate technical and organizational measures to protect your data</li>
            <li>Access to personal data is restricted to authorized personnel only</li>
            <li>We regularly review and update our security measures</li>
            <li>Data is encrypted both in transit and at rest</li>
        </ul>
        
        <h3>6. DATA RETENTION</h3>
        <p>We retain your personal data for as long as necessary to:</p>
        <ul>
            <li>Fulfill the purposes for which it was collected</li>
            <li>Comply with legal and regulatory requirements</li>
            <li>Resolve disputes and enforce agreements</li>
            <li>Generally, loan data is retained for 7 years after loan closure</li>
        </ul>
        
        <h3>7. CONTACT US</h3>
        <p>For any data protection inquiries or to exercise your rights, contact us at:</p>
        <ul>
            <li>Email: privacy@intellicash.com</li>
            <li>Phone: +254 700 000 000</li>
            <li>Address: [Your Company Address]</li>
        </ul>
        ';
    }

    private function getUgandaTerms()
    {
        return '
        <h4>LOAN TERMS AND CONDITIONS - UGANDA</h4>
        <p><strong>Governing Law:</strong> Laws of the Republic of Uganda</p>
        <p><strong>Regulatory Compliance:</strong> Bank of Uganda (BOU) Guidelines</p>
        
        <h5>1. ELIGIBILITY REQUIREMENTS</h5>
        <ul>
            <li>Ugandan citizen or resident with valid identification</li>
            <li>Minimum age of 18 years as per the Constitution of Uganda</li>
            <li>Valid Tax Identification Number (TIN)</li>
            <li>Proof of income and employment as per BOU requirements</li>
            <li>Bank account with a licensed financial institution in Uganda</li>
        </ul>
        
        <h5>2. INTEREST RATES AND CHARGES</h5>
        <ul>
            <li>Interest rates calculated as per BOU guidelines and Financial Institutions Act</li>
            <li>Late payment penalties as per BOU guidelines</li>
            <li>All charges disclosed in accordance with BOU consumer protection guidelines</li>
        </ul>
        ';
    }

    private function getUgandaPrivacy()
    {
        return '
        <h4>PRIVACY POLICY - UGANDA</h4>
        <p><strong>Governing Law:</strong> Data Protection and Privacy Act, 2019 (Uganda)</p>
        <p><strong>Regulatory Compliance:</strong> Personal Data Protection Office (PDPO)</p>
        
        <h5>1. DATA COLLECTION</h5>
        <p>We collect personal data as required by the Financial Institutions Act, BOU guidelines, and Data Protection and Privacy Act, 2019.</p>
        ';
    }

    private function getTanzaniaTerms()
    {
        return '
        <h4>LOAN TERMS AND CONDITIONS - TANZANIA</h4>
        <p><strong>Governing Law:</strong> Laws of the United Republic of Tanzania</p>
        <p><strong>Regulatory Compliance:</strong> Bank of Tanzania (BOT) Guidelines</p>
        
        <h5>1. ELIGIBILITY REQUIREMENTS</h5>
        <ul>
            <li>Tanzanian citizen or resident with valid identification</li>
            <li>Minimum age of 18 years as per the Constitution of Tanzania</li>
            <li>Valid Tax Identification Number (TIN)</li>
            <li>Proof of income and employment as per BOT requirements</li>
            <li>Bank account with a licensed financial institution in Tanzania</li>
        </ul>
        
        <h5>2. INTEREST RATES AND CHARGES</h5>
        <ul>
            <li>Interest rates calculated as per BOT guidelines and Banking and Financial Institutions Act</li>
            <li>Late payment penalties as per BOT guidelines</li>
            <li>All charges disclosed in accordance with BOT consumer protection guidelines</li>
        </ul>
        ';
    }

    private function getTanzaniaPrivacy()
    {
        return '
        <h4>PRIVACY POLICY - TANZANIA</h4>
        <p><strong>Governing Law:</strong> Personal Data Protection Act, 2022 (Tanzania)</p>
        <p><strong>Regulatory Compliance:</strong> Personal Data Protection Commission (PDPC)</p>
        
        <h5>1. DATA COLLECTION</h5>
        <p>We collect personal data as required by the Banking and Financial Institutions Act, BOT guidelines, and Personal Data Protection Act, 2022.</p>
        ';
    }
}
