<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LoanTermsAndPrivacy;
use App\Models\Tenant;
use App\Models\User;

class KenyanLegalComplianceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->command->info('No tenants found. Skipping Kenyan legal compliance seeding.');
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
            
            // Create additional specialized terms for different loan products
            $this->createMicrofinanceTerms($tenant, $adminUser);
            $this->createSMEBusinessTerms($tenant, $adminUser);
            $this->createAgriculturalTerms($tenant, $adminUser);
        }
    }
    
    private function createMicrofinanceTerms($tenant, $adminUser)
    {
        LoanTermsAndPrivacy::create([
            'tenant_id' => $tenant->id,
            'loan_product_id' => null, // Can be linked to specific microfinance products
            'title' => 'Microfinance Loan Terms and Conditions',
            'terms_and_conditions' => $this->getMicrofinanceTerms(),
            'privacy_policy' => $this->getStandardPrivacyPolicy(),
            'is_active' => true,
            'is_default' => false,
            'version' => '1.0',
            'effective_date' => now(),
            'created_by' => $adminUser->id,
        ]);
    }
    
    private function createSMEBusinessTerms($tenant, $adminUser)
    {
        LoanTermsAndPrivacy::create([
            'tenant_id' => $tenant->id,
            'loan_product_id' => null,
            'title' => 'SME Business Loan Terms and Conditions',
            'terms_and_conditions' => $this->getSMEBusinessTerms(),
            'privacy_policy' => $this->getStandardPrivacyPolicy(),
            'is_active' => true,
            'is_default' => false,
            'version' => '1.0',
            'effective_date' => now(),
            'created_by' => $adminUser->id,
        ]);
    }
    
    private function createAgriculturalTerms($tenant, $adminUser)
    {
        LoanTermsAndPrivacy::create([
            'tenant_id' => $tenant->id,
            'loan_product_id' => null,
            'title' => 'Agricultural Loan Terms and Conditions',
            'terms_and_conditions' => $this->getAgriculturalTerms(),
            'privacy_policy' => $this->getStandardPrivacyPolicy(),
            'is_active' => true,
            'is_default' => false,
            'version' => '1.0',
            'effective_date' => now(),
            'created_by' => $adminUser->id,
        ]);
    }
    
    private function getMicrofinanceTerms()
    {
        return '
        <h4>MICROFINANCE LOAN TERMS AND CONDITIONS</h4>
        <p><strong>Governing Law:</strong> Microfinance Act, 2006 and Central Bank of Kenya Guidelines</p>
        <p><strong>Regulatory Compliance:</strong> CBK Prudential Guidelines for Microfinance Banks</p>
        
        <h5>1. MICROFINANCE SPECIFIC REQUIREMENTS</h5>
        <ul>
            <li>Loans are provided under the Microfinance Act, 2006</li>
            <li>Maximum loan amount as per CBK microfinance guidelines</li>
            <li>Group lending arrangements may be required</li>
            <li>Regular group meetings and savings requirements</li>
            <li>Peer pressure and social collateral as security</li>
        </ul>
        
        <h5>2. ELIGIBILITY CRITERIA</h5>
        <ul>
            <li>Kenyan citizen or resident with valid ID</li>
            <li>Minimum age 18 years</li>
            <li>Proof of income from micro-enterprise or employment</li>
            <li>Group membership (where applicable)</li>
            <li>Regular savings history with the institution</li>
        </ul>
        
        <h5>3. INTEREST RATES AND CHARGES</h5>
        <ul>
            <li>Interest rates as per CBK microfinance guidelines</li>
            <li>Processing fees not exceeding 3% of loan amount</li>
            <li>Insurance charges as required by law</li>
            <li>Late payment penalties as per CBK guidelines</li>
        </ul>
        
        <h5>4. REPAYMENT TERMS</h5>
        <ul>
            <li>Weekly, bi-weekly, or monthly repayments</li>
            <li>Group guarantee for individual loans</li>
            <li>Grace periods for agricultural loans</li>
            <li>Flexible repayment during difficult periods</li>
        </ul>
        ';
    }
    
    private function getSMEBusinessTerms()
    {
        return '
        <h4>SME BUSINESS LOAN TERMS AND CONDITIONS</h4>
        <p><strong>Governing Law:</strong> Banking Act, Companies Act, and CBK Guidelines</p>
        <p><strong>Regulatory Compliance:</strong> CBK Guidelines for SME Lending</p>
        
        <h5>1. SME SPECIFIC REQUIREMENTS</h5>
        <ul>
            <li>Business must be registered with the Registrar of Companies</li>
            <li>Valid KRA PIN and tax compliance certificate</li>
            <li>Business plan and financial projections required</li>
            <li>Minimum business operating period of 6 months</li>
            <li>Annual turnover as per CBK SME definition</li>
        </ul>
        
        <h5>2. COLLATERAL REQUIREMENTS</h5>
        <ul>
            <li>Business assets as primary collateral</li>
            <li>Personal guarantees from directors/owners</li>
            <li>Insurance coverage for business assets</li>
            <li>Floating charge over business assets</li>
            <li>Assignment of receivables where applicable</li>
        </ul>
        
        <h5>3. FINANCIAL COVENANTS</h5>
        <ul>
            <li>Maintenance of minimum debt service coverage ratio</li>
            <li>Regular submission of financial statements</li>
            <li>Restrictions on additional borrowing without consent</li>
            <li>Maintenance of minimum working capital</li>
        </ul>
        
        <h5>4. MONITORING AND REPORTING</h5>
        <ul>
            <li>Quarterly business performance reviews</li>
            <li>Annual audited financial statements</li>
            <li>Site visits and business monitoring</li>
            <li>Compliance with business plan objectives</li>
        </ul>
        ';
    }
    
    private function getAgriculturalTerms()
    {
        return '
        <h4>AGRICULTURAL LOAN TERMS AND CONDITIONS</h4>
        <p><strong>Governing Law:</strong> Banking Act, Agriculture Act, and CBK Guidelines</p>
        <p><strong>Regulatory Compliance:</strong> CBK Guidelines for Agricultural Lending</p>
        
        <h5>1. AGRICULTURAL SPECIFIC REQUIREMENTS</h5>
        <ul>
            <li>Proof of land ownership or lease agreement</li>
            <li>Agricultural project feasibility study</li>
            <li>Weather insurance coverage (where available)</li>
            <li>Technical support and extension services</li>
            <li>Market linkages and off-take agreements</li>
        </ul>
        
        <h5>2. SEASONAL CONSIDERATIONS</h5>
        <ul>
            <li>Grace periods aligned with crop cycles</li>
            <li>Flexible repayment during harvest seasons</li>
            <li>Weather-related repayment adjustments</li>
            <li>Insurance against natural disasters</li>
            <li>Technical advisory services</li>
        </ul>
        
        <h5>3. COLLATERAL AND SECURITY</h5>
        <ul>
            <li>Agricultural land as primary collateral</li>
            <li>Crop liens and harvest assignments</li>
            <li>Equipment and machinery security</li>
            <li>Livestock as collateral (where applicable)</li>
            <li>Government guarantee schemes participation</li>
        </ul>
        
        <h5>4. RISK MANAGEMENT</h5>
        <ul>
            <li>Weather index insurance</li>
            <li>Price risk management tools</li>
            <li>Diversification requirements</li>
            <li>Regular farm visits and monitoring</li>
            <li>Emergency credit facilities</li>
        </ul>
        ';
    }
    
    private function getStandardPrivacyPolicy()
    {
        return '
        <h4>PRIVACY POLICY</h4>
        <p><strong>Governing Law:</strong> Data Protection Act, 2019 (Kenya)</p>
        <p><strong>Regulatory Compliance:</strong> Office of the Data Protection Commissioner (ODPC)</p>
        
        <h5>1. DATA COLLECTION</h5>
        <p>We collect personal data as required by the Banking Act, CBK guidelines, and other applicable Kenyan laws.</p>
        
        <h5>2. DATA PROCESSING</h5>
        <p>Your data is processed in accordance with the Data Protection Act, 2019 and CBK requirements.</p>
        
        <h5>3. DATA SHARING</h5>
        <p>We may share information with credit reference bureaus, regulatory authorities, and other parties as required by law.</p>
        
        <h5>4. YOUR RIGHTS</h5>
        <p>You have rights under the Data Protection Act, 2019 including access, rectification, and erasure of your personal data.</p>
        
        <h5>5. CONTACT</h5>
        <p>For questions about this privacy policy, contact our Data Protection Officer.</p>
        ';
    }
}