<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LandingPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        if ($this->command) {
            $this->command->info('Seeding landing page default data...');
        }

        // Default landing page settings
        $landingPageSettings = [
            // Core site settings
            [
                'name' => 'site_title',
                'value' => 'Grow With Us',
            ],
            [
                'name' => 'company_name',
                'value' => 'Intelli Cash',
            ],
            [
                'name' => 'landing_page_status',
                'value' => '1',
            ],
            [
                'name' => 'phone',
                'value' => '0768706799',
            ],
            [
                'name' => 'email',
                'value' => 'intelliwealthafrica@gmail.com',
            ],
            [
                'name' => 'address',
                'value' => 'Intelli-Wealth Limited, Ramco Court Bellevue, South C, Nairobi City, 00100, Nairobi, Kenya',
            ],
            [
                'name' => 'currency',
                'value' => 'KES',
            ],
            [
                'name' => 'timezone',
                'value' => 'Africa/Nairobi',
            ],
            [
                'name' => 'language',
                'value' => 'English---us',
            ],
            [
                'name' => 'date_format',
                'value' => 'Y-m-d',
            ],
            [
                'name' => 'time_format',
                'value' => '24',
            ],
            [
                'name' => 'currency_position',
                'value' => 'left',
            ],
            [
                'name' => 'thousand_sep',
                'value' => ',',
            ],
            [
                'name' => 'decimal_sep',
                'value' => '.',
            ],
            [
                'name' => 'decimal_places',
                'value' => '2',
            ],
            [
                'name' => 'member_signup',
                'value' => '1',
            ],
            [
                'name' => 'email_verification',
                'value' => '0',
            ],
            [
                'name' => 'backend_direction',
                'value' => 'ltr',
            ],
            [
                'name' => 'mail_type',
                'value' => 'sendmail',
            ],
            [
                'name' => 'from_email',
                'value' => 'support@intellicash.africa',
            ],
            [
                'name' => 'from_name',
                'value' => 'Intelli-Cash Team',
            ],
            [
                'name' => 'smtp_host',
                'value' => 'intellicash.africa',
            ],
            [
                'name' => 'smtp_port',
                'value' => '465',
            ],
            [
                'name' => 'smtp_username',
                'value' => 'support@intellicash.africa',
            ],
            [
                'name' => 'smtp_password',
                'value' => 'Karibu@2025',
            ],
            [
                'name' => 'smtp_encryption',
                'value' => 'ssl',
            ],
            // PWA Settings
            [
                'name' => 'pwa_enabled',
                'value' => '1',
            ],
            [
                'name' => 'pwa_app_name',
                'value' => 'IntelliCash',
            ],
            [
                'name' => 'pwa_short_name',
                'value' => 'IntelliCash',
            ],
            [
                'name' => 'pwa_description',
                'value' => 'Progressive Web App for IntelliCash - Manage your finances efficiently',
            ],
            [
                'name' => 'pwa_theme_color',
                'value' => '#007bff',
            ],
            [
                'name' => 'pwa_background_color',
                'value' => '#ffffff',
            ],
            [
                'name' => 'pwa_display_mode',
                'value' => 'standalone',
            ],
            [
                'name' => 'pwa_orientation',
                'value' => 'portrait-primary',
            ],
            [
                'name' => 'pwa_icon_192',
                'value' => 'pwa-icon-192x192.png',
            ],
            [
                'name' => 'pwa_icon_512',
                'value' => 'pwa-icon-512x512.png',
            ],
            [
                'name' => 'pwa_shortcut_dashboard',
                'value' => '1',
            ],
            [
                'name' => 'pwa_shortcut_transactions',
                'value' => '1',
            ],
            [
                'name' => 'pwa_shortcut_profile',
                'value' => '1',
            ],
            [
                'name' => 'pwa_offline_support',
                'value' => '1',
            ],
            [
                'name' => 'pwa_cache_strategy',
                'value' => 'cache-first',
            ],
        ];

        // Insert or update settings
        foreach ($landingPageSettings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['name' => $setting['name']],
                ['value' => $setting['value']]
            );
        }

        // Home page content (stored as JSON in home_page setting)
        $homePageData = [
            'title' => 'Home - Intelli Cash',
            'hero_heading' => 'We are Intelli-Cash<br>Smart Financial Solutions for Africa',
            'hero_sub_heading' => 'Transform your savings group into a powerful financial cooperative with our comprehensive VSLA management platform. Built for African communities, designed for growth.',
            'features_status' => '1',
            'features_heading' => 'Everything Your Community Needs to Thrive',
            'features_sub_heading' => 'From savings cycles to loan management, social funds to external partnerships - we\'ve got every aspect of your financial cooperative covered.',
            'pricing_status' => '1',
            'pricing_heading' => 'Flexible Plans for Every Community Size',
            'pricing_sub_heading' => 'Whether you\'re a small village group or a large cooperative network, our transparent pricing grows with your success.',
            'blog_status' => '1',
            'blog_heading' => 'Insights & Success Stories',
            'blog_sub_heading' => 'Learn from thriving communities across Africa and discover best practices for managing your financial cooperative.',
            'testimonials_status' => '1',
            'testimonials_heading' => 'Trusted by Communities Across Africa',
            'testimonials_sub_heading' => 'Hear from group leaders and members who\'ve transformed their financial futures with our platform.',
            'newsletter_status' => '1',
            'newsletter_heading' => 'Stay Connected to Your Financial Future',
            'newsletter_sub_heading' => 'Get the latest updates on features, success stories, and financial tips delivered straight to your inbox',
        ];

        DB::table('settings')->updateOrInsert(
            ['name' => 'home_page'],
            ['value' => json_encode($homePageData)]
        );

        // About page content
        $aboutPageData = [
            'title' => 'About Us',
            'section_1_heading' => 'What is Intelli-Cash?',
            'section_1_content' => '<p class="whitespace-normal break-words">Intelli-Cash is a comprehensive digital platform designed specifically for African Village Savings and Loan Associations (VSLAs), chamas, and social groups seeking to strengthen their financial operations. Our SaaS-based solution transforms traditional community savings groups into powerful financial cooperatives while preserving the trust and solidarity that makes them successful.</p>
<p class="whitespace-normal break-words">Built with multi-tenant architecture, we serve both community groups and lending organizations, enabling seamless management of savings cycles, loan disbursements, social funds, and external funding partnerships. Our platform integrates with mobile money systems like M-Pesa and KCB BUNI, making financial transactions as simple as sending a text message.</p>',
            'section_2_heading' => 'Who We Are?',
            'section_2_content' => '<p class="whitespace-normal break-words"><strong>Intelli-Cash is a product of Intelli-Wealth Limited, a social enterprise focused on empowering African agricultural communities through accessible digital financial tools and comprehensive financial literacy programs.</strong></p>
<p class="whitespace-normal break-words">We are technologists, financial experts, and community advocates who recognize that Africa\'s financial future lies in strengthening existing community structures, rather than replacing them. Our parent company, Intelli-Wealth Limited, has worked directly with VSLAs, chamas, and social groups across Kenya, Uganda, and Tanzania, providing financial education and digital solutions that truly serve grassroots agricultural communities.</p>',
            'section_3_heading' => 'What We Offer?',
            'section_3_content' => '<p class="whitespace-normal break-words"><strong>Our platform provides complete group management with integrated mobile money transactions and automated SMS notifications, designed specifically for agricultural communities and their unique financial needs.</strong></p>
<ul class="[&:not(:last-child)_ul]:pb-1 [&:not(:last-child)_ol]:pb-1 list-disc space-y-1.5 pl-7">
<li class="whitespace-normal break-words">Multi-fund management supporting internal savings, chama contributions, agricultural loans, and external lending partnerships</li>
<li class="whitespace-normal break-words">Automated loan calculations with flexible repayment schedules tailored for seasonal agricultural income patterns</li>
<li class="whitespace-normal break-words">Real-time transaction processing through M-Pesa and KCB BUNI integrations for all group activities</li>
<li class="whitespace-normal break-words">SMS notifications for contributions, loan approvals, harvest season reminders, and payment schedules via Africa\'s Talking</li>
</ul>',
            'team_heading' => 'People Behind Your Financial Empowerment',
            'team_sub_heading' => 'Our diverse team at Intelli-Wealth Limited combines deep technical expertise with grassroots understanding of agricultural finance, chama operations, and rural community dynamics to build solutions that actually work for farming communities.',
        ];

        DB::table('settings')->updateOrInsert(
            ['name' => 'about_page'],
            ['value' => json_encode($aboutPageData)]
        );

        // Pricing page content
        $pricingPageData = [
            'title' => 'Pricing',
            'pricing_heading' => 'Simple, Transparent Pricing That Grows With Your Community',
            'pricing_sub_heading' => 'Whether you\'re a small village chama, a thriving VSLA, or a large agricultural cooperative, our flexible subscription plans are designed to support your financial growth without breaking the bank. No hidden fees, no setup costs - just honest pricing for honest communities.',
        ];

        DB::table('settings')->updateOrInsert(
            ['name' => 'pricing_page'],
            ['value' => json_encode($pricingPageData)]
        );

        // Features page content
        $featuresPageData = [
            'title' => 'Features',
            'features_heading' => 'Everything Your Community Needs to Thrive',
            'features_sub_heading' => 'From savings cycles to loan management, social funds to external partnerships - we\'ve got every aspect of your financial cooperative covered.',
        ];

        DB::table('settings')->updateOrInsert(
            ['name' => 'features_page'],
            ['value' => json_encode($featuresPageData)]
        );

        // Blogs page content
        $blogsPageData = [
            'title' => 'Blogs',
            'blogs_heading' => 'Stories, Tips & Insights from Thriving Communities',
            'blogs_sub_heading' => 'Discover success stories from VSLAs, chamas, and agricultural cooperatives across Africa. Learn financial management tips, explore digital transformation journeys, and get expert advice on growing your community\'s wealth through cooperative finance.',
        ];

        DB::table('settings')->updateOrInsert(
            ['name' => 'blogs_page'],
            ['value' => json_encode($blogsPageData)]
        );

        // FAQ page content
        $faqPageData = [
            'title' => 'FAQ',
            'faq_heading' => 'Frequently Asked Questions',
            'faq_sub_heading' => 'Get quick answers to common questions about managing your VSLA, chama, or agricultural cooperative with Intelli-Cash. From setup and mobile money integration to loan management and group operations - we\'ve got you covered.',
        ];

        DB::table('settings')->updateOrInsert(
            ['name' => 'faq_page'],
            ['value' => json_encode($faqPageData)]
        );

        // Contact page content
        $contactPageData = [
            'title' => 'Get Support for Your Community',
            'contact_form_heading' => 'Ready to Transform Your Community\'s Finance?',
            'contact_form_sub_heading' => 'Ready to Transform Your Community\'s Finance?',
            'contact_info_heading' => ['Still Have Questions?', 'Opening hours', 'Office Address'],
            'contact_info_content' => [
                ' <span>Call Us We Will Be Happy To Help<br><a href="tel:+254768706799">+254768706799</a></span>',
                '<span>Monday - Friday<br>9AM - 8PM (EAT)</span>',
                '<span>Intelli-Wealth Limited, Ramco Court Bellevue, South C, Nairobi City, 00100, Nairobi, Kenya</span>'
            ],
            'facebook_link' => '#',
            'linkedin_link' => '#',
            'twitter_link' => '#',
            'youtube_link' => '#',
        ];

        DB::table('settings')->updateOrInsert(
            ['name' => 'contact_page'],
            ['value' => json_encode($contactPageData)]
        );

        // Header/Footer page content
        $headerFooterPageData = [
            'footer_color' => '#167b6a',
            'footer_text_color' => '#FFF',
            'widget_1_heading' => 'About Us',
            'widget_1_content' => 'Intelli-Cash is a product of Intelli-Wealth Limited, a social enterprise dedicated to empowering African communities through digital financial tools and comprehensive financial literacy programs. We transform traditional VSLAs, chamas, and agricultural cooperatives into powerful digital entities.',
            'widget_2_heading' => 'Customer Service',
            'widget_2_menus' => ['faq', 'contact', 'privacy-policy', 'terms-condition'],
            'widget_3_heading' => 'Quick Explore',
            'widget_3_menus' => ['home', 'about', 'features', 'pricing', 'farmers-revolving-fund-kitty'],
            'copyright_text' => '<p>Copyright © 2025 <a href="https://intellicash.africa" target="_blank">Intelli-Wealth Limited</a> - All Rights Reserved.</p>',
            'custom_css' => '',
            'custom_js' => '',
        ];

        DB::table('settings')->updateOrInsert(
            ['name' => 'header_footer_page'],
            ['value' => json_encode($headerFooterPageData)]
        );

        // GDPR Cookie Consent page content
        $gdprCookieConsentPageData = [
            'cookie_consent_status' => '1',
            'cookie_message' => 'We use cookies to enhance your browsing experience, serve personalized ads or content, and analyze our traffic. By clicking "Accept", you consent to our use of cookies.',
        ];

        DB::table('settings')->updateOrInsert(
            ['name' => 'gdpr_cookie_consent_page'],
            ['value' => json_encode($gdprCookieConsentPageData)]
        );

        // Terms & Conditions page content (truncated for brevity)
        $termsConditionPageData = [
            'title' => 'Terms & Condition',
            'content' => '<h1>Terms and Conditions</h1>
<p><strong>Effective Date:</strong> September 17, 2025<br /><strong>Last Updated:</strong> September 17, 2025</p>
<h2>1. Introduction</h2>
<p>Welcome to Intelli-Cash, a digital platform operated by Intelli-Wealth Limited ("Company," "we," "us," or "our"). These Terms and Conditions ("Terms") govern your use of the Intelli-Cash platform, website, mobile applications, and related services (collectively, the "Service") designed for Village Savings and Loan Associations (VSLAs), chamas, agricultural cooperatives, and social groups in Africa.</p>
<p>By accessing or using our Service, you agree to be bound by these Terms. If you disagree with any part of these Terms, you may not access the Service.</p>
<h2>2. Definitions</h2>
<ul>
<li><strong>"User"</strong> refers to any individual or organization accessing or using the Service</li>
<li><strong>"Group"</strong> refers to VSLAs, chamas, agricultural cooperatives, or social groups using the platform</li>
<li><strong>"Tenant"</strong> refers to registered groups or lending organizations on our multi-tenant platform</li>
<li><strong>"Member"</strong> refers to individuals belonging to a registered Group</li>
<li><strong>"Administrator"</strong> refers to designated users with administrative privileges within a Group</li>
<li><strong>"Platform"</strong> refers to the Intelli-Cash software-as-a-service platform</li>
</ul>
<p><em>[Full terms content continues...]</em></p>',
        ];

        DB::table('settings')->updateOrInsert(
            ['name' => 'terms-condition_page'],
            ['value' => json_encode($termsConditionPageData)]
        );

        // Privacy Policy page content (truncated for brevity)
        $privacyPolicyPageData = [
            'title' => 'Privacy Policy',
            'content' => '<h1>Privacy Policy</h1>
<p><strong>Effective Date:</strong> September 17, 2025<br /><strong>Last Updated:</strong> September 17, 2025</p>
<h2>1. Introduction</h2>
<p>Intelli-Wealth Limited ("Company," "we," "us," or "our") operates the Intelli-Cash platform, a digital financial management solution for Village Savings and Loan Associations (VSLAs), chamas, agricultural cooperatives, and social groups across Africa. This Privacy Policy explains how we collect, use, disclose, and safeguard your personal information when you use our services.</p>
<p>We are committed to protecting your privacy and handling your personal data in accordance with the Kenya Data Protection Act, 2019, and other applicable data protection laws. By using our services, you consent to the practices described in this Privacy Policy.</p>
<h2>2. Information We Collect</h2>
<h3>2.1 Personal Information</h3>
<p>We collect personal information that you voluntarily provide to us, including:</p>
<p><strong>Account Information:</strong></p>
<ul>
<li>Full name and contact details (phone number, email address)</li>
<li>National identification number or passport number</li>
<li>Physical address and location information</li>
<li>Date of birth and gender</li>
<li>Employment information and income details</li>
</ul>
<p><em>[Full privacy policy content continues...]</em></p>',
        ];

        DB::table('settings')->updateOrInsert(
            ['name' => 'privacy-policy_page'],
            ['value' => json_encode($privacyPolicyPageData)]
        );

        // Home page media settings
        $homePageMediaData = [
            'hero_image' => 'file_13137600191758116405.jpg',
            'newsletter_bg_image' => 'file_10147109191758116499.jpg',
        ];

        DB::table('settings')->updateOrInsert(
            ['name' => 'home_page_media'],
            ['value' => json_encode($homePageMediaData)]
        );

        // About page media settings
        $aboutPageMediaData = [
            'about_image' => 'file_18554035331758117319.jpg',
        ];

        DB::table('settings')->updateOrInsert(
            ['name' => 'about_page_media'],
            ['value' => json_encode($aboutPageMediaData)]
        );

        if ($this->command) {
            $this->command->info('Landing page default data seeded successfully!');
            $this->command->info('Created default settings for:');
            $this->command->info('- Site configuration and branding');
            $this->command->info('- Home page content and media');
            $this->command->info('- About, Pricing, Features, Blogs, FAQ pages');
            $this->command->info('- Contact information and footer');
            $this->command->info('- Terms & Conditions and Privacy Policy');
            $this->command->info('- GDPR Cookie Consent');
            $this->command->info('- PWA (Progressive Web App) settings');
            $this->command->info('- Email and SMTP configuration');
        }
    }
}
