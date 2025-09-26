<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LegalTemplate;

class MultiCountryLegalTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create legal templates for multiple countries
        $countries = [
            [
                'country_code' => 'KEN',
                'country_name' => 'Kenya',
                'template_name' => 'General Commercial Loan Terms',
                'template_type' => 'general',
                'version' => '1.0',
                'terms_and_conditions' => $this->getKenyaGeneralTerms(),
                'privacy_policy' => $this->getKenyaGeneralPrivacy(),
                'description' => 'General commercial loan terms for Kenya',
                'applicable_laws' => ['Banking Act (Cap 488)', 'Data Protection Act, 2019', 'Credit Reference Bureau Act, 2013'],
                'regulatory_bodies' => ['Central Bank of Kenya (CBK)', 'Office of the Data Protection Commissioner (ODPC)'],
                'is_active' => true,
                'is_system_template' => true,
                'language_code' => 'en'
            ],
            [
                'country_code' => 'UGA',
                'country_name' => 'Uganda',
                'template_name' => 'General Commercial Loan Terms',
                'template_type' => 'general',
                'version' => '1.0',
                'terms_and_conditions' => $this->getUgandaGeneralTerms(),
                'privacy_policy' => $this->getUgandaGeneralPrivacy(),
                'description' => 'General commercial loan terms for Uganda',
                'applicable_laws' => ['Financial Institutions Act, 2004', 'Data Protection and Privacy Act, 2019'],
                'regulatory_bodies' => ['Bank of Uganda (BOU)', 'Personal Data Protection Office (PDPO)'],
                'is_active' => true,
                'is_system_template' => true,
                'language_code' => 'en'
            ],
            [
                'country_code' => 'TZA',
                'country_name' => 'Tanzania',
                'template_name' => 'General Commercial Loan Terms',
                'template_type' => 'general',
                'version' => '1.0',
                'terms_and_conditions' => $this->getTanzaniaGeneralTerms(),
                'privacy_policy' => $this->getTanzaniaGeneralPrivacy(),
                'description' => 'General commercial loan terms for Tanzania',
                'applicable_laws' => ['Banking and Financial Institutions Act, 2006', 'Personal Data Protection Act, 2022'],
                'regulatory_bodies' => ['Bank of Tanzania (BOT)', 'Personal Data Protection Commission (PDPC)'],
                'is_active' => true,
                'is_system_template' => true,
                'language_code' => 'en'
            ],
            [
                'country_code' => 'RWA',
                'country_name' => 'Rwanda',
                'template_name' => 'General Commercial Loan Terms',
                'template_type' => 'general',
                'version' => '1.0',
                'terms_and_conditions' => $this->getRwandaGeneralTerms(),
                'privacy_policy' => $this->getRwandaGeneralPrivacy(),
                'description' => 'General commercial loan terms for Rwanda',
                'applicable_laws' => ['Law No. 02/2010 on Banking', 'Law No. 058/2021 on Personal Data Protection'],
                'regulatory_bodies' => ['National Bank of Rwanda (BNR)', 'Data Protection Authority (DPA)'],
                'is_active' => true,
                'is_system_template' => true,
                'language_code' => 'en'
            ],
            [
                'country_code' => 'GHA',
                'country_name' => 'Ghana',
                'template_name' => 'General Commercial Loan Terms',
                'template_type' => 'general',
                'version' => '1.0',
                'terms_and_conditions' => $this->getGhanaGeneralTerms(),
                'privacy_policy' => $this->getGhanaGeneralPrivacy(),
                'description' => 'General commercial loan terms for Ghana',
                'applicable_laws' => ['Banks and Specialised Deposit-Taking Institutions Act, 2016', 'Data Protection Act, 2012'],
                'regulatory_bodies' => ['Bank of Ghana (BOG)', 'Data Protection Commission (DPC)'],
                'is_active' => true,
                'is_system_template' => true,
                'language_code' => 'en'
            ],
        ];

        foreach ($countries as $countryData) {
            LegalTemplate::firstOrCreate(
                [
                    'country_code' => $countryData['country_code'],
                    'template_name' => $countryData['template_name'],
                    'template_type' => $countryData['template_type'],
                ],
                $countryData
            );
        }

        $this->command->info('Multi-country legal templates seeded successfully!');
        $this->command->info('Created legal templates for: Kenya, Uganda, Tanzania, Rwanda, Ghana');
    }

    private function getKenyaGeneralTerms()
    {
        return '
        <h2>GENERAL COMMERCIAL LOAN TERMS - KENYA</h2>
        <p><strong>Governing Law:</strong> Laws of the Republic of Kenya</p>
        <p><strong>Regulatory Compliance:</strong> Central Bank of Kenya (CBK) Guidelines</p>
        
        <h3>1. ELIGIBILITY REQUIREMENTS</h3>
        <ul>
            <li>Kenyan citizen or resident with valid identification</li>
            <li>Minimum age of 18 years</li>
            <li>Valid Kenya Revenue Authority (KRA) PIN certificate</li>
            <li>Proof of income and employment</li>
            <li>Bank account with a licensed financial institution</li>
        </ul>
        
        <h3>2. INTEREST RATES AND CHARGES</h3>
        <ul>
            <li>Interest rates calculated as per CBK guidelines</li>
            <li>Late payment penalties as per CBK guidelines</li>
            <li>All charges disclosed in accordance with CBK consumer protection guidelines</li>
        </ul>
        ';
    }

    private function getKenyaGeneralPrivacy()
    {
        return '
        <h2>PRIVACY POLICY - KENYA</h2>
        <p><strong>Governing Law:</strong> Data Protection Act, 2019 (Kenya)</p>
        <p><strong>Regulatory Compliance:</strong> Office of the Data Protection Commissioner (ODPC)</p>
        
        <h3>1. DATA COLLECTION</h3>
        <p>We collect personal data as required by the Banking Act, CBK guidelines, and Data Protection Act, 2019.</p>
        ';
    }

    private function getUgandaGeneralTerms()
    {
        return '
        <h2>GENERAL COMMERCIAL LOAN TERMS - UGANDA</h2>
        <p><strong>Governing Law:</strong> Laws of the Republic of Uganda</p>
        <p><strong>Regulatory Compliance:</strong> Bank of Uganda (BOU) Guidelines</p>
        
        <h3>1. ELIGIBILITY REQUIREMENTS</h3>
        <ul>
            <li>Ugandan citizen or resident with valid identification</li>
            <li>Minimum age of 18 years</li>
            <li>Valid Tax Identification Number (TIN)</li>
            <li>Proof of income and employment</li>
            <li>Bank account with a licensed financial institution</li>
        </ul>
        ';
    }

    private function getUgandaGeneralPrivacy()
    {
        return '
        <h2>PRIVACY POLICY - UGANDA</h2>
        <p><strong>Governing Law:</strong> Data Protection and Privacy Act, 2019 (Uganda)</p>
        <p><strong>Regulatory Compliance:</strong> Personal Data Protection Office (PDPO)</p>
        
        <h3>1. DATA COLLECTION</h3>
        <p>We collect personal data as required by the Financial Institutions Act, BOU guidelines, and Data Protection and Privacy Act, 2019.</p>
        ';
    }

    private function getTanzaniaGeneralTerms()
    {
        return '
        <h2>GENERAL COMMERCIAL LOAN TERMS - TANZANIA</h2>
        <p><strong>Governing Law:</strong> Laws of the United Republic of Tanzania</p>
        <p><strong>Regulatory Compliance:</strong> Bank of Tanzania (BOT) Guidelines</p>
        
        <h3>1. ELIGIBILITY REQUIREMENTS</h3>
        <ul>
            <li>Tanzanian citizen or resident with valid identification</li>
            <li>Minimum age of 18 years</li>
            <li>Valid Tax Identification Number (TIN)</li>
            <li>Proof of income and employment</li>
            <li>Bank account with a licensed financial institution</li>
        </ul>
        ';
    }

    private function getTanzaniaGeneralPrivacy()
    {
        return '
        <h2>PRIVACY POLICY - TANZANIA</h2>
        <p><strong>Governing Law:</strong> Personal Data Protection Act, 2022 (Tanzania)</p>
        <p><strong>Regulatory Compliance:</strong> Personal Data Protection Commission (PDPC)</p>
        
        <h3>1. DATA COLLECTION</h3>
        <p>We collect personal data as required by the Banking and Financial Institutions Act, BOT guidelines, and Personal Data Protection Act, 2022.</p>
        ';
    }

    private function getRwandaGeneralTerms()
    {
        return '
        <h2>GENERAL COMMERCIAL LOAN TERMS - RWANDA</h2>
        <p><strong>Governing Law:</strong> Laws of the Republic of Rwanda</p>
        <p><strong>Regulatory Compliance:</strong> National Bank of Rwanda (BNR) Guidelines</p>
        
        <h3>1. ELIGIBILITY REQUIREMENTS</h3>
        <ul>
            <li>Rwandan citizen or resident with valid identification</li>
            <li>Minimum age of 18 years</li>
            <li>Valid Tax Identification Number (TIN)</li>
            <li>Proof of income and employment</li>
            <li>Bank account with a licensed financial institution</li>
        </ul>
        ';
    }

    private function getRwandaGeneralPrivacy()
    {
        return '
        <h2>PRIVACY POLICY - RWANDA</h2>
        <p><strong>Governing Law:</strong> Law No. 058/2021 on Personal Data Protection (Rwanda)</p>
        <p><strong>Regulatory Compliance:</strong> Data Protection Authority (DPA)</p>
        
        <h3>1. DATA COLLECTION</h3>
        <p>We collect personal data as required by Law No. 02/2010 on Banking, BNR guidelines, and Law No. 058/2021 on Personal Data Protection.</p>
        ';
    }

    private function getGhanaGeneralTerms()
    {
        return '
        <h2>GENERAL COMMERCIAL LOAN TERMS - GHANA</h2>
        <p><strong>Governing Law:</strong> Laws of the Republic of Ghana</p>
        <p><strong>Regulatory Compliance:</strong> Bank of Ghana (BOG) Guidelines</p>
        
        <h3>1. ELIGIBILITY REQUIREMENTS</h3>
        <ul>
            <li>Ghanaian citizen or resident with valid identification</li>
            <li>Minimum age of 18 years</li>
            <li>Valid Tax Identification Number (TIN)</li>
            <li>Proof of income and employment</li>
            <li>Bank account with a licensed financial institution</li>
        </ul>
        ';
    }

    private function getGhanaGeneralPrivacy()
    {
        return '
        <h2>PRIVACY POLICY - GHANA</h2>
        <p><strong>Governing Law:</strong> Data Protection Act, 2012 (Ghana)</p>
        <p><strong>Regulatory Compliance:</strong> Data Protection Commission (DPC)</p>
        
        <h3>1. DATA COLLECTION</h3>
        <p>We collect personal data as required by the Banks and Specialised Deposit-Taking Institutions Act, 2016, BOG guidelines, and Data Protection Act, 2012.</p>
        ';
    }
}
