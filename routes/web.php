<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Include debug routes
require_once __DIR__ . '/debug.php';
use App\Http\Controllers\LoanController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Select2Controller;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\InterestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuarantorController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\Auth\SocialController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\CustomFieldController;
use App\Http\Controllers\LoanPaymentController;
use App\Http\Controllers\LoanProductController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DepositMethodController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\DepositRequestController;
use App\Http\Controllers\Install\UpdateController;
use App\Http\Controllers\LoanCollateralController;
use App\Http\Controllers\MemberDocumentController;
use App\Http\Controllers\SavingsAccountController;
use App\Http\Controllers\SavingsProductController;
use App\Http\Controllers\SuperAdmin\FaqController;
use App\Http\Controllers\TenantSettingsController;
use App\Http\Controllers\WithdrawMethodController;
use App\Http\Controllers\AutomaticMethodController;
use App\Http\Controllers\BankTransactionController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\ApiModuleController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PayrollDeductionController;
use App\Http\Controllers\PayrollBenefitController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\Member\AuditController as MemberAuditController;
use App\Http\Controllers\SystemAdmin\AuditController as SystemAdminAuditController;
use App\Http\Controllers\VotingController;
use App\Http\Controllers\VotingPositionController;
use App\Http\Controllers\Install\InstallController;
use App\Http\Controllers\SuperAdmin\PageController;
use App\Http\Controllers\SuperAdmin\PostController;
use App\Http\Controllers\SuperAdmin\TeamController;
use App\Http\Controllers\Website\WebsiteController;
use App\Http\Controllers\WithdrawRequestController;
use App\Http\Controllers\SuperAdmin\BackupController;
use App\Http\Controllers\SuperAdmin\TenantController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\SuperAdmin\ContactController;
use App\Http\Controllers\SuperAdmin\FeatureController;
use App\Http\Controllers\SuperAdmin\PackageController;
use App\Http\Controllers\SuperAdmin\UtilityController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\SuperAdmin\LanguageController;
use App\Http\Controllers\TransactionCategoryController;
use App\Http\Controllers\SuperAdmin\TestimonialController;
use App\Http\Controllers\SuperAdmin\OfflineMethodController;
use App\Http\Controllers\SuperAdmin\PaymentGatewayController;
use App\Http\Controllers\SuperAdmin\EmailSubscriberController;
use App\Http\Controllers\SuperAdmin\SubscriptionPaymentController;
use App\Http\Controllers\SuperAdmin\NotificationTemplateController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SubscriptionGateway\Mollie\ProcessController as MollieProcessController;
use App\Http\Controllers\SubscriptionGateway\PayPal\ProcessController as PayPalProcessController;
use App\Http\Controllers\SubscriptionGateway\Stripe\ProcessController as StripeProcessController;
use App\Http\Controllers\PWAController;
use App\Http\Controllers\PWAIconController;
use App\Http\Controllers\SubscriptionGateway\Offline\ProcessController as OfflineProcessController;
use App\Http\Controllers\SubscriptionGateway\Paystack\ProcessController as PaystackProcessController;
use App\Http\Controllers\SubscriptionGateway\Razorpay\ProcessController as RazorpayProcessController;
use App\Http\Controllers\SubscriptionGateway\Instamojo\ProcessController as InstamojoProcessController;
use App\Http\Controllers\SubscriptionGateway\Flutterwave\ProcessController as FlutterwaveProcessController;
use App\Http\Controllers\SubscriptionGateway\Buni\ProcessController as BuniProcessController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

$ev = env('APP_INSTALLED', true) == true && get_option('email_verification', 0) == 1 ? true : false;

Route::group(['middleware' => ['install']], function () use ($ev) {

    // Public Receipt Verification Routes (no authentication required)
    Route::prefix('public')->group(function () {
        Route::get('/receipt/verify/{token}', [App\Http\Controllers\PublicReceiptController::class, 'verify'])->name('public.receipt.verify');
        Route::post('/receipt/verify-qr-data', [App\Http\Controllers\PublicReceiptController::class, 'verifyQrData'])->name('public.receipt.verify.qr-data');
    });

    // PWA Routes (no auth required)
    Route::get('/manifest.json', [PWAController::class, 'manifest'])->name('pwa.manifest');
    Route::get('/pwa/status', [PWAController::class, 'getStatus'])->name('pwa.status');
    Route::get('/pwa/install-prompt', [PWAController::class, 'showInstallPrompt'])->name('pwa.install-prompt');
    Route::get('/offline', function() {
        return response()->file(public_path('offline'));
    })->name('pwa.offline');

    Route::prefix('admin')->group(function () {
        Route::get('/', function () {
            return redirect()->route('admin.login');
        });
        Route::get('/login', [LoginController::class, 'showAdminLoginForm'])->name('admin.login');
        Route::post('/login', [LoginController::class, 'login'])->name('admin.login.post');
        Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('admin.password.request');
        Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('admin.password.email');
        Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('admin.password.reset');
        Route::post('password/reset', [ResetPasswordController::class, 'reset'])->name('admin.password.update');
    });

    Route::prefix('{tenant}')->middleware('tenant')->group(function () {
        Route::get('/members_signup', [RegisterController::class, 'showMembersSignupForm'])->name('tenant.members_signup');
        Route::post('/members_signup', [RegisterController::class, 'members_signup'])->name('tenant.members_signup');
        Route::get('/login', [LoginController::class, 'showTenantLoginForm'])->name('tenant.login');
        Route::post('/login', [LoginController::class, 'login'])->name('tenant.login');
        Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('tenant.password.request');
        Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('tenant.password.email');
        Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('tenant.password.reset');
        Route::post('password/reset', [ResetPasswordController::class, 'reset'])->name('tenant.password.update');
    });

    Auth::routes([
        'login'  => false,
        'reset'  => false,
        'verify' => $ev,
    ]);
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'showTenants']);
    Route::get('/logout', [LoginController::class, 'logout']);

    Route::get('/2fa', [TwoFactorController::class, 'show'])->name('2fa')->middleware('auth');

    $initialMiddleware = ['auth', '2fa'];
    if ($ev == 1) {
        array_push($initialMiddleware, 'verified');
    }

    Route::group(['middleware' => $initialMiddleware], function () {

        /** Super Admin Only Routes **/
        Route::name('admin.')->prefix('admin')->middleware(['superadmin'])->group(function () {
            Route::get('dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard.index');

            //2FA Verification
            Route::post('/2fa', [TwoFactorController::class, 'verify'])->name('2fa.verify');

            //Profile Controller
            Route::get('profile', [ProfileController::class, 'index'])->name('profile.index');
            Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::post('profile/update', [ProfileController::class, 'update'])->name('profile.update')->middleware('demo');
            Route::get('profile/change_password', [ProfileController::class, 'change_password'])->name('profile.change_password');
            Route::post('profile/update_password', [ProfileController::class, 'update_password'])->name('profile.update_password')->middleware('demo');
            Route::match(['get', 'post'], 'profile/enable_2fa', [ProfileController::class, 'enable_2fa'])->name('profile.enable_2fa');
            Route::match(['get', 'post'], 'profile/disable_2fa', [ProfileController::class, 'disable_2fa'])->name('profile.disable_2fa');
            Route::get('profile/notification_mark_as_read/{id}', [ProfileController::class, 'notification_mark_as_read'])->name('profile.notification_mark_as_read');
            Route::get('profile/show_notification/{id}', [ProfileController::class, 'show_notification'])->name('profile.show_notification');

            //Subscription Payments
            Route::get('subscription_payments/{id}/approve_payout_requests', [SubscriptionPaymentController::class, 'approve_payment_requests'])->name('subscription_payments.approve_payment_requests');
            Route::match(['get', 'post'], 'subscription_payments/{id}/reject_payout_requests', [SubscriptionPaymentController::class, 'reject_payment_requests'])->name('subscription_payments.reject_payment_requests');
            Route::get('subscription_payments/get_table_data', [SubscriptionPaymentController::class, 'get_table_data']);
            Route::resource('subscription_payments', SubscriptionPaymentController::class)->except([
                'destroy',
            ])->middleware("demo:PUT|PATCH|DELETE");

            //Tenant Controller
            Route::post('tenants/send_email', [TenantController::class, 'send_email'])->name('tenants.send_email')->middleware('demo');
            Route::get('tenants/get_table_data', [TenantController::class, 'get_table_data']);
            Route::resource('tenants', TenantController::class)->middleware("demo:PUT|PATCH|DELETE");

            //System Admin Audit Trail
            Route::get('audit/get_table_data', [SystemAdminAuditController::class, 'getTableData'])->name('audit.get_table_data');
            Route::get('audit/statistics', [SystemAdminAuditController::class, 'statistics'])->name('audit.statistics');
            Route::get('audit/export', [SystemAdminAuditController::class, 'export'])->name('audit.export');
            Route::resource('audit', SystemAdminAuditController::class)->only(['index', 'show'])->names('audit');

            Route::group(['middleware' => 'demo'], function () {

                //Language Controller
                Route::get('languages/{lang}/edit_website_language', [LanguageController::class, 'edit_website_language'])->name('languages.edit_website_language');
                Route::resource('languages', LanguageController::class);

                //Utility Controller
                Route::match(['get', 'post'], 'general_settings/{store?}', [UtilityController::class, 'settings'])->name('settings.update_settings')->middleware('throttle:10,1');
                Route::post('upload_logo', [UtilityController::class, 'upload_logo'])->name('settings.uplaod_logo')->middleware('throttle:5,1');
                Route::post('remove_cache', [UtilityController::class, 'remove_cache'])->name('settings.remove_cache')->middleware('throttle:3,1');
                Route::post('send_test_email', [UtilityController::class, 'send_test_email'])->name('settings.send_test_email')->middleware('throttle:3,1');

                //Data Backup
                Route::get('backups', [BackupController::class, 'index'])->name('backup.index');
                Route::get('backups/create', [BackupController::class, 'create_backup'])->name('backup.create')->middleware("demo:GET");
                Route::get('backups/restore', [BackupController::class, 'show_restore_form'])->name('backup.restore');
                Route::post('backups/restore', [BackupController::class, 'restore_backup'])->name('backup.restore');
                Route::get('backups/{file}/download', [BackupController::class, 'download'])->name('backup.download')->middleware("demo:GET");
                Route::delete('backups/{file}/destroy', [BackupController::class, 'destroy'])->name('backup.destroy');

                //Notification Template
                Route::resource('notification_templates', NotificationTemplateController::class)->only([
                    'index', 'edit', 'update', 'show',
                ])->middleware("demo");

                //Package Controller
                Route::resource('packages', PackageController::class);

                //Payment Gateways
                Route::resource('payment_gateways', PaymentGatewayController::class)->except([
                    'create', 'store', 'show', 'destroy',
                ]);

                //Offline Gateways
                Route::resource('offline_methods', OfflineMethodController::class)->except('show');

                //Page Controller
                Route::post('pages/store_default_pages/{slug?}', [PageController::class, 'store_default_pages'])->name('pages.default_pages.store');
                Route::get('pages/default_pages/{slug?}', [PageController::class, 'default_pages'])->name('pages.default_pages');
                Route::resource('pages', PageController::class)->except('show');

                //FAQ Controller
                Route::resource('faqs', FaqController::class)->except('show');

                //Features Controller
                Route::resource('features', FeatureController::class)->except('show');

                //Testimonial Controller
                Route::resource('testimonials', TestimonialController::class)->except('show');

                //Team Controller
                Route::resource('posts', PostController::class)->except('show');

                //Team Controller
                Route::resource('teams', TeamController::class)->except('show');

                //Email Subscribers
                Route::match(['get', 'post'], 'email_subscribers/send_email', [EmailSubscriberController::class, 'send_email'])->name('email_subscribers.send_email');
                Route::get('email_subscribers/export', [EmailSubscriberController::class, 'export'])->name('email_subscribers.export');
                Route::get('email_subscribers/get_table_data', [EmailSubscriberController::class, 'get_table_data']);
                Route::get('email_subscribers', [EmailSubscriberController::class, 'index'])->name('email_subscribers.index');
                Route::delete('email_subscribers/{id}/destroy', [EmailSubscriberController::class, 'destroy'])->name('email_subscribers.destroy');

                //Contact Messages
                Route::get('contact_messages/get_table_data', [ContactController::class, 'get_table_data']);
                Route::resource('contact_messages', ContactController::class)->only(['index', 'show', 'destroy']);
            });
        });

        //Tenant Dashboard
        Route::prefix('{tenant}')->middleware(['tenant', 'tenant.global'])->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

            //E-Signature Management
            Route::prefix('esignature')->name('esignature.')->group(function () {
                // Test route
                Route::get('test', function() {
                    return 'E-Signature test route works!';
                })->name('test');
                
                // Documents - Following the same pattern as regular documents
                Route::get('esignature-documents/statistics', [App\Http\Controllers\ESignatureController::class, 'statistics'])->name('esignature-documents.statistics');
                Route::get('esignature-documents/{id}/audit-trail', [App\Http\Controllers\ESignatureController::class, 'auditTrail'])->name('esignature-documents.audit-trail');
                Route::post('esignature-documents/{id}/send', [App\Http\Controllers\ESignatureController::class, 'send'])->name('esignature-documents.send');
                Route::post('esignature-documents/{id}/cancel', [App\Http\Controllers\ESignatureController::class, 'cancel'])->name('esignature-documents.cancel');
                Route::post('esignature-documents/{id}/send-reminders', [App\Http\Controllers\ESignatureController::class, 'sendReminders'])->name('esignature-documents.send-reminders');
                Route::get('esignature-documents/{id}/download', [App\Http\Controllers\ESignatureController::class, 'download'])->name('esignature-documents.download');
                Route::get('esignature-documents/{id}/download-signed', [App\Http\Controllers\ESignatureController::class, 'downloadSigned'])->name('esignature-documents.download-signed');
                Route::get('esignature-documents/{id}/view', [App\Http\Controllers\ESignatureController::class, 'view'])->name('esignature-documents.view');
                Route::resource('esignature-documents', App\Http\Controllers\ESignatureController::class);
                
                // Fields
                Route::resource('fields', App\Http\Controllers\ESignatureFieldController::class);
            });

            // Receipt QR Code Routes (Shared - accessible to both admin and customer)
            Route::prefix('receipt')->group(function () {
                Route::get('/verify/{token}', [App\Http\Controllers\ReceiptVerificationController::class, 'verify'])->name('receipt.verify');
                Route::post('/verify-qr-data', [App\Http\Controllers\ReceiptVerificationController::class, 'verifyQrData'])->name('receipt.verify.qr-data');
            });

            //2FA Verification
            Route::post('/2fa', [TwoFactorController::class, 'verify'])->name('2fa.verify');

            //Profile Controller
            Route::get('profile', [ProfileController::class, 'index'])->name('profile.index');
            Route::get('profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::post('profile/update', [ProfileController::class, 'update'])->name('profile.update')->middleware('demo');
            Route::get('profile/change_password', [ProfileController::class, 'change_password'])->name('profile.change_password');
            Route::post('profile/update_password', [ProfileController::class, 'update_password'])->name('profile.update_password')->middleware('demo');
            Route::match(['get', 'post'], 'profile/enable_2fa', [ProfileController::class, 'enable_2fa'])->name('profile.enable_2fa');
            Route::match(['get', 'post'], 'profile/disable_2fa', [ProfileController::class, 'disable_2fa'])->name('profile.disable_2fa');
            Route::get('profile/notification_mark_as_read/{id}', [ProfileController::class, 'notification_mark_as_read'])->name('profile.notification_mark_as_read');
            Route::get('profile/show_notification/{id}', [ProfileController::class, 'show_notification'])->name('profile.show_notification');

            //Message Controllers
            Route::get('/messages/compose', [MessageController::class, 'compose'])->name('messages.compose');
            Route::post('/messages/send', [MessageController::class, 'send'])->name('messages.send');
            Route::get('/messages/inbox', [MessageController::class, 'inbox'])->name('messages.inbox');
            Route::get('/messages/sent', [MessageController::class, 'sentItems'])->name('messages.sent');
            Route::get('/messages/{id}', [MessageController::class, 'show'])->name('messages.show');
            Route::get('/messages/reply/{id}', [MessageController::class, 'reply'])->name('messages.reply');
            Route::post('/messages/reply/{id}', [MessageController::class, 'sendReply'])->name('messages.sendReply');
            Route::get('/messages/{id}/download_attachment', [MessageController::class, 'download_attachment'])->name('messages.download_attachment');

                //Ajax Select2 Controller
                Route::get('ajax/get_table_data', [Select2Controller::class, 'get_table_data']);

            /** Tenant Admin Only Routes **/
            Route::middleware('tenant.admin')->group(function () {
                Route::get('membership', [MembershipController::class, 'index'])->name('membership.index');

                //Branch Controller
                Route::resource('branches', BranchController::class)->middleware("demo:PUT|PATCH|DELETE");

                //Savings Products
                Route::resource('savings_products', SavingsProductController::class);

                //Transaction Category
                Route::resource('transaction_categories', TransactionCategoryController::class);

                //Loan Products
                Route::resource('loan_products', LoanProductController::class)->except('show');

                //Loan Terms and Privacy Policy
                // Route::resource('loan_terms', App\Http\Controllers\Admin\LoanTermsAndPrivacyController::class);
                // Route::post('loan_terms/{id}/set_default', [App\Http\Controllers\Admin\LoanTermsAndPrivacyController::class, 'setDefault'])->name('loan_terms.set_default');

                //Voting Management
                Route::prefix('voting')->name('voting.')->group(function () {
                    // Positions
                    Route::resource('positions', VotingPositionController::class);
                    Route::post('positions/{position}/toggle', [VotingPositionController::class, 'toggleActive'])->name('positions.toggle');
                    
                    // Elections
                    Route::resource('elections', VotingController::class);
                    Route::post('elections/{election}/start', [VotingController::class, 'start'])->name('elections.start');
                    Route::post('elections/{election}/close', [VotingController::class, 'close'])->name('elections.close');
                    Route::get('elections/{election}/results', [VotingController::class, 'results'])->name('elections.results');
                    
                    // Candidates
                    Route::get('elections/{election}/candidates', [VotingController::class, 'manageCandidates'])->name('candidates.manage');
                    Route::post('elections/{election}/candidates', [VotingController::class, 'addCandidate'])->name('candidates.add');
                    Route::delete('candidates/{candidate}', [VotingController::class, 'removeCandidate'])->name('candidates.remove');
                });

                // Route::post('loan_terms/{id}/toggle_active', [App\Http\Controllers\Admin\LoanTermsAndPrivacyController::class, 'toggleActive'])->name('loan_terms.toggle_active');
                Route::get('loan_terms/get_terms_for_product', [App\Http\Controllers\Admin\LoanTermsAndPrivacyController::class, 'getTermsForProduct'])->name('loan_terms.get_terms_for_product');
                // Route::get('loan_terms/get_template_details', [App\Http\Controllers\Admin\LoanTermsAndPrivacyController::class, 'getTemplateDetails'])->name('loan_terms.get_template_details');
                // Route::get('loan_terms/get_available_countries', [App\Http\Controllers\Admin\LoanTermsAndPrivacyController::class, 'getAvailableCountries'])->name('loan_terms.get_available_countries');
                // Route::get('loan_terms/get_template_types_for_country', [App\Http\Controllers\Admin\LoanTermsAndPrivacyController::class, 'getTemplateTypesForCountry'])->name('loan_terms.get_template_types_for_country');

                //Template-based Loan Terms Creation
                // Route::get('loan_terms/create_from_template', [App\Http\Controllers\AdvancedLoanManagementController::class, 'createLoanTermsFromTemplate'])->name('loan_terms.create_from_template');
                // Route::post('loan_terms/store_from_template', [App\Http\Controllers\AdvancedLoanManagementController::class, 'storeLoanTermsFromTemplate'])->name('loan_terms.store_from_template');
                
                //Legal Template Management
                // Route::get('legal_templates', [App\Http\Controllers\AdvancedLoanManagementController::class, 'indexLegalTemplates'])->name('legal_templates.index');
                // Route::get('legal_templates/{id}/edit', [App\Http\Controllers\AdvancedLoanManagementController::class, 'editLegalTemplate'])->name('legal_templates.edit');
                // Route::put('legal_templates/{id}', [App\Http\Controllers\AdvancedLoanManagementController::class, 'updateLegalTemplate'])->name('legal_templates.update');
                

                //Expense Categories
                Route::resource('expense_categories', ExpenseCategoryController::class)->except('show');

                //User Management
                Route::get('users/get_table_data', [UserController::class, 'get_table_data']);
                Route::resource('users', UserController::class)->middleware("demo:PUT|PATCH|DELETE");

                //User Roles
                Route::resource('roles', RoleController::class)->middleware("demo:PUT|PATCH|DELETE");

                //Deposit Methods
                Route::resource('deposit_methods', DepositMethodController::class)->except([
                    'show',
                ]);

                //Automatic Methods
                Route::resource('automatic_methods', AutomaticMethodController::class)->except([
                    'show', 'create', 'store', 'destroy',
                ]);

                //Withdraw Methods
                Route::resource('withdraw_methods', WithdrawMethodController::class)->except([
                    'show',
                ]);

                //Permission Controller
                Route::get('permission/access_control', [PermissionController::class, 'index'])->name('permission.index');
                Route::get('permission/access_control/{user_id?}', [PermissionController::class, 'show'])->name('permission.show');
                Route::post('permission/store', [PermissionController::class, 'store'])->name('permission.store');

                //Tenant Settings Controller
                Route::post('settings/upload_logo', [TenantSettingsController::class, 'upload_logo'])->name('settings.upload_logo')->middleware('throttle:5,1');
                Route::post('settings/send_test_email', [TenantSettingsController::class, 'send_test_email'])->name('settings.send_test_email')->middleware('throttle:3,1');
                Route::post('settings/store_email_settings', [TenantSettingsController::class, 'store_email_settings'])->name('settings.store_email_settings')->middleware('throttle:10,1');
                Route::post('settings/store_currency_settings', [TenantSettingsController::class, 'store_currency_settings'])->name('settings.store_currency_settings')->middleware('throttle:10,1');
                Route::post('settings/store_general_settings', [TenantSettingsController::class, 'store_general_settings'])->name('settings.store_general_settings')->middleware('throttle:10,1');
                Route::get('settings', [TenantSettingsController::class, 'index'])->name('settings.index');

                //Module Management
                Route::get('modules', [App\Http\Controllers\ModuleController::class, 'index'])->name('modules.index');
                Route::post('modules/toggle-vsla', [App\Http\Controllers\ModuleController::class, 'toggleVsla'])->name('modules.toggle_vsla');
                Route::post('modules/toggle-api', [App\Http\Controllers\ModuleController::class, 'toggleApi'])->name('modules.toggle_api');
                Route::post('modules/toggle-qr-code', [App\Http\Controllers\ModuleController::class, 'toggleQrCode'])->name('modules.toggle_qr_code');
                Route::post('modules/toggle-asset-management', [App\Http\Controllers\ModuleController::class, 'toggleAssetManagement'])->name('modules.toggle_asset_management');
                Route::post('modules/toggle-esignature', [App\Http\Controllers\ModuleController::class, 'toggleESignature'])->name('modules.toggle_esignature');
                
                //QR Code Module Configuration
                Route::get('modules/qr-code/configure', [App\Http\Controllers\ModuleController::class, 'configureQrCode'])->name('modules.qr_code.configure');
                Route::get('modules/qr-code/guide', [App\Http\Controllers\ModuleController::class, 'qrCodeGuide'])->name('modules.qr_code.guide');
                Route::post('modules/qr-code/update', [App\Http\Controllers\ModuleController::class, 'updateQrCodeConfig'])->name('modules.qr_code.update');
                Route::post('modules/qr-code/test-ethereum', [App\Http\Controllers\ModuleController::class, 'testEthereumConnection'])->name('modules.qr_code.test_ethereum');
                Route::post('modules/toggle-payroll', [App\Http\Controllers\ModuleController::class, 'togglePayroll'])->name('modules.toggle_payroll');
                
                //Payroll Module Configuration
                Route::get('modules/payroll/configure', [App\Http\Controllers\ModuleController::class, 'configurePayroll'])->name('modules.payroll.configure');
                Route::post('modules/payroll/update', [App\Http\Controllers\ModuleController::class, 'updatePayrollConfig'])->name('modules.payroll.update');

                // Payroll Module Routes
                Route::prefix('payroll')->middleware(['payroll_module'])->group(function () {
                    // Payroll Periods
                    Route::resource('periods', PayrollController::class)->names([
                        'index' => 'payroll.periods.index',
                        'create' => 'payroll.periods.create',
                        'store' => 'payroll.periods.store',
                        'show' => 'payroll.periods.show',
                        'edit' => 'payroll.periods.edit',
                        'update' => 'payroll.periods.update',
                        'destroy' => 'payroll.periods.destroy',
                    ]);
                    Route::post('periods/{id}/process', [PayrollController::class, 'process'])->name('payroll.periods.process')->middleware('payroll_rate_limit:5,5');
                    Route::post('periods/{id}/complete', [PayrollController::class, 'complete'])->name('payroll.periods.complete')->middleware('payroll_rate_limit:3,10');
                    Route::post('periods/{id}/cancel', [PayrollController::class, 'cancel'])->name('payroll.periods.cancel')->middleware('payroll_rate_limit:3,10');
                    Route::post('periods/{id}/add-employee', [PayrollController::class, 'addEmployee'])->name('payroll.periods.add-employee')->middleware('payroll_rate_limit:20,1');
                    Route::post('periods/{id}/remove-employee', [PayrollController::class, 'removeEmployee'])->name('payroll.periods.remove-employee')->middleware('payroll_rate_limit:20,1');
                    Route::get('periods/{id}/report', [PayrollController::class, 'report'])->name('payroll.periods.report');
                    Route::get('periods/{id}/export/{format?}', [PayrollController::class, 'export'])->name('payroll.periods.export');

                    // Employees
                    Route::resource('employees', EmployeeController::class)->names([
                        'index' => 'payroll.employees.index',
                        'create' => 'payroll.employees.create',
                        'store' => 'payroll.employees.store',
                        'show' => 'payroll.employees.show',
                        'edit' => 'payroll.employees.edit',
                        'update' => 'payroll.employees.update',
                        'destroy' => 'payroll.employees.destroy',
                    ]);
                    Route::post('employees/{id}/toggle-status', [EmployeeController::class, 'toggleStatus'])->name('payroll.employees.toggle-status');
                    Route::get('employees/{id}/payroll-history', [EmployeeController::class, 'payrollHistory'])->name('payroll.employees.payroll-history');
                    Route::get('employees/{id}/deductions', [EmployeeController::class, 'deductions'])->name('payroll.employees.deductions');
                    Route::post('employees/{id}/deductions', [EmployeeController::class, 'assignDeductions'])->name('payroll.employees.assign-deductions');
                    Route::get('employees/{id}/benefits', [EmployeeController::class, 'benefits'])->name('payroll.employees.benefits');
                    Route::post('employees/{id}/benefits', [EmployeeController::class, 'assignBenefits'])->name('payroll.employees.assign-benefits');

                    // Payroll Deductions
                    Route::get('deductions', [PayrollDeductionController::class, 'index'])->name('payroll.deductions.index');
                    Route::get('deductions/create', [PayrollDeductionController::class, 'create'])->name('payroll.deductions.create');
                    Route::post('deductions', [PayrollDeductionController::class, 'store'])->name('payroll.deductions.store');
                    Route::get('deductions/{id}', [PayrollDeductionController::class, 'show'])->name('payroll.deductions.show');
                    Route::get('deductions/{id}/edit', [PayrollDeductionController::class, 'edit'])->name('payroll.deductions.edit');
                    Route::put('deductions/{id}', [PayrollDeductionController::class, 'update'])->name('payroll.deductions.update');
                    Route::delete('deductions/{id}', [PayrollDeductionController::class, 'destroy'])->name('payroll.deductions.destroy');
                    Route::post('deductions/{id}/toggle-status', [PayrollDeductionController::class, 'toggleStatus'])->name('payroll.deductions.toggle-status');
                    Route::post('deductions/create-defaults', [PayrollDeductionController::class, 'createDefaults'])->name('payroll.deductions.create-defaults');

                    // Payroll Benefits
                    Route::get('benefits', [PayrollBenefitController::class, 'index'])->name('payroll.benefits.index');
                    Route::get('benefits/create', [PayrollBenefitController::class, 'create'])->name('payroll.benefits.create');
                    Route::post('benefits', [PayrollBenefitController::class, 'store'])->name('payroll.benefits.store');
                    Route::get('benefits/{id}', [PayrollBenefitController::class, 'show'])->name('payroll.benefits.show');
                    Route::get('benefits/{id}/edit', [PayrollBenefitController::class, 'edit'])->name('payroll.benefits.edit');
                    Route::put('benefits/{id}', [PayrollBenefitController::class, 'update'])->name('payroll.benefits.update');
                    Route::delete('benefits/{id}', [PayrollBenefitController::class, 'destroy'])->name('payroll.benefits.destroy');
                    Route::post('benefits/{id}/toggle-status', [PayrollBenefitController::class, 'toggleStatus'])->name('payroll.benefits.toggle-status');
                    Route::post('benefits/create-defaults', [PayrollBenefitController::class, 'createDefaults'])->name('payroll.benefits.create-defaults');
                });

                // Asset Management Module Routes
                Route::prefix('asset-management')->middleware(['asset_module'])->group(function () {
                    // Dashboard
                    Route::get('/', [App\Http\Controllers\AssetManagementDashboardController::class, 'index'])->name('asset-management.dashboard');
                    // Asset Categories
                    Route::resource('asset-categories', App\Http\Controllers\AssetCategoryController::class);
                    Route::post('asset-categories/{id}/toggle-status', [App\Http\Controllers\AssetCategoryController::class, 'toggleStatus'])->name('asset-categories.toggle-status');

                    // Assets
                    Route::get('assets/available-for-lease', [App\Http\Controllers\AssetController::class, 'availableForLease'])->name('assets.available-for-lease');
                    Route::get('assets/{asset}/lease-form', [App\Http\Controllers\AssetController::class, 'leaseForm'])->name('assets.lease-form');
                    Route::post('assets/{asset}/create-lease', [App\Http\Controllers\AssetController::class, 'createLease'])->name('assets.create-lease');
                    Route::get('assets/{asset}/sell', [App\Http\Controllers\AssetController::class, 'sell'])->name('assets.sell');
                    Route::post('assets/{asset}/process-sale', [App\Http\Controllers\AssetController::class, 'processSale'])->name('assets.process-sale');
                    Route::resource('assets', App\Http\Controllers\AssetController::class);

                    // Asset Leases
                    Route::post('asset-leases/{lease}/complete', [App\Http\Controllers\AssetLeaseController::class, 'complete'])->name('asset-leases.complete');
                    Route::post('asset-leases/{lease}/cancel', [App\Http\Controllers\AssetLeaseController::class, 'cancel'])->name('asset-leases.cancel');
                    Route::post('asset-leases/{lease}/mark-overdue', [App\Http\Controllers\AssetLeaseController::class, 'markOverdue'])->name('asset-leases.mark-overdue');
                    Route::resource('asset-leases', App\Http\Controllers\AssetLeaseController::class);

                    // Lease Requests (Admin)
                    Route::post('lease-requests/{leaseRequest}/approve', [App\Http\Controllers\LeaseRequestController::class, 'approve'])->name('lease-requests.approve');
                    Route::post('lease-requests/{leaseRequest}/reject', [App\Http\Controllers\LeaseRequestController::class, 'reject'])->name('lease-requests.reject');
                    Route::get('lease-requests/available-assets', [App\Http\Controllers\LeaseRequestController::class, 'getAvailableAssets'])->name('lease-requests.available-assets');
                    Route::get('lease-requests/asset-details', [App\Http\Controllers\LeaseRequestController::class, 'getAssetDetails'])->name('lease-requests.asset-details');
                    Route::resource('lease-requests', App\Http\Controllers\LeaseRequestController::class);

                    // Asset Maintenance
                    Route::get('asset-maintenance/overdue', [App\Http\Controllers\AssetMaintenanceController::class, 'overdue'])->name('asset-maintenance.overdue');
                    Route::post('asset-maintenance/{maintenance}/mark-in-progress', [App\Http\Controllers\AssetMaintenanceController::class, 'markInProgress'])->name('asset-maintenance.mark-in-progress');
                    Route::post('asset-maintenance/{maintenance}/complete', [App\Http\Controllers\AssetMaintenanceController::class, 'complete'])->name('asset-maintenance.complete');
                    Route::post('asset-maintenance/{maintenance}/cancel', [App\Http\Controllers\AssetMaintenanceController::class, 'cancel'])->name('asset-maintenance.cancel');
                    Route::resource('asset-maintenance', App\Http\Controllers\AssetMaintenanceController::class);

                    // Asset Reports
                    Route::get('reports', [App\Http\Controllers\AssetReportsController::class, 'index'])->name('asset-reports.index');
                    Route::get('reports/valuation', [App\Http\Controllers\AssetReportsController::class, 'valuation'])->name('asset-reports.valuation');
                    Route::get('reports/profit-loss', [App\Http\Controllers\AssetReportsController::class, 'profitLoss'])->name('asset-reports.profit-loss');
                    Route::get('reports/lease-performance', [App\Http\Controllers\AssetReportsController::class, 'leasePerformance'])->name('asset-reports.lease-performance');
                    Route::get('reports/maintenance', [App\Http\Controllers\AssetReportsController::class, 'maintenance'])->name('asset-reports.maintenance');
                    Route::get('reports/utilization', [App\Http\Controllers\AssetReportsController::class, 'utilization'])->name('asset-reports.utilization');
                });

                //API Management
                Route::prefix('api')->group(function () {
                    Route::get('/', [ApiModuleController::class, 'index'])->name('api.index');
                    Route::get('/create', [ApiModuleController::class, 'create'])->name('api.create');
                    Route::post('/', [ApiModuleController::class, 'store'])->name('api.store');
                    // Specific routes must come before parameterized routes
                    Route::get('/documentation', [ApiModuleController::class, 'documentation'])->name('api.documentation');
                    Route::post('/test', [ApiModuleController::class, 'testEndpoint'])->name('api.test');
                    // Parameterized routes come after specific routes
                    Route::get('/{id}', [ApiModuleController::class, 'show'])->name('api.show');
                    Route::get('/{id}/edit', [ApiModuleController::class, 'edit'])->name('api.edit');
                    Route::put('/{id}', [ApiModuleController::class, 'update'])->name('api.update');
                    Route::post('/{id}/revoke', [ApiModuleController::class, 'revoke'])->name('api.revoke');
                    Route::post('/{id}/regenerate-secret', [ApiModuleController::class, 'regenerateSecret'])->name('api.regenerate-secret');
                    Route::delete('/{id}', [ApiModuleController::class, 'destroy'])->name('api.destroy');
                });

                //Currency Controller
                Route::resource('currency', CurrencyController::class);

                //PWA Routes
                Route::get('pwa/install-prompt', [PWAController::class, 'showInstallPrompt'])->name('pwa.install-prompt');
                Route::post('pwa/generate-icons', [PWAIconController::class, 'generateIcons'])->name('pwa.generate-icons');
                Route::post('pwa/create-default-icons', [PWAIconController::class, 'createDefaultIcons'])->name('pwa.create-default-icons');
                Route::get('pwa/test', function() {
                    return view('pwa.test');
                })->name('pwa.test');

                //Notification Template
                Route::resource('email_templates', EmailTemplateController::class)->only([
                    'index', 'edit', 'update', 'show',
                ])->middleware("demo");

            });

            /** Tenant Role based user Routes **/
            Route::middleware('tenant.user')->group(function () {
                //Dashboard Widget
                Route::get('dashboard/total_customer_widget', [DashboardController::class, 'dashboard_widget'])->name('dashboard.total_customer_widget');
                Route::get('dashboard/deposit_requests_widget', [DashboardController::class, 'dashboard_widget'])->name('dashboard.deposit_requests_widget');
                Route::get('dashboard/withdraw_requests_widget', [DashboardController::class, 'dashboard_widget'])->name('dashboard.withdraw_requests_widget');
                Route::get('dashboard/loan_requests_widget', [DashboardController::class, 'dashboard_widget'])->name('dashboard.loan_requests_widget');

                //Voting for Members
                Route::prefix('voting')->name('voting.')->group(function () {
                    Route::get('elections', [VotingController::class, 'index'])->name('elections.index');
                    Route::get('elections/{election}', [VotingController::class, 'show'])->name('elections.show');
                    Route::get('elections/{election}/vote', [VotingController::class, 'vote'])->name('elections.vote');
                    Route::post('elections/{election}/vote', [VotingController::class, 'submitVote'])->name('vote.submit');
                    Route::get('elections/{election}/results', [VotingController::class, 'results'])->name('elections.results');
                    Route::get('elections/{election}/security-report', [VotingController::class, 'securityReport'])->name('elections.security-report');
                });
                Route::get('dashboard/expense_overview_widget', [DashboardController::class, 'dashboard_widget'])->name('dashboard.expense_overview_widget');
                Route::get('dashboard/deposit_withdraw_analytics', [DashboardController::class, 'dashboard_widget'])->name('dashboard.deposit_withdraw_analytics');
                Route::get('dashboard/recent_transaction_widget', [DashboardController::class, 'dashboard_widget'])->name('dashboard.recent_transaction_widget');
                Route::get('dashboard/due_loan_list', [DashboardController::class, 'dashboard_widget'])->name('dashboard.due_loan_list');
                Route::get('dashboard/active_loan_balances', [DashboardController::class, 'dashboard_widget'])->name('dashboard.active_loan_balances');

                //Member Controller
                Route::match(['get', 'post'], 'members/import', [MemberController::class, 'import'])->name('members.import');
                Route::match(['get', 'post'], 'members/accept_request/{id}', [MemberController::class, 'accept_request'])->name('members.accept_request');
                Route::get('members/reject_request/{id}', [MemberController::class, 'reject_request'])->name('members.reject_request');
                Route::get('members/pending_requests', [MemberController::class, 'pending_requests'])->name('members.pending_requests');
                Route::get('members/get_member_transaction_data/{member_id}', [MemberController::class, 'get_member_transaction_data']);
                Route::get('members/get_table_data', [MemberController::class, 'get_table_data']);
                Route::post('members/send_email', [MemberController::class, 'send_email'])->name('members.send_email');
                Route::post('members/send_sms', [MemberController::class, 'send_sms'])->name('members.send_sms');
                Route::resource('members', MemberController::class)->middleware("demo:PUT|PATCH|DELETE");

                //Custom Field Controller
                Route::resource('custom_fields', CustomFieldController::class)->except(['index', 'show'])->middleware("demo");
                Route::get('custom_fields/{table}', [CustomFieldController::class, 'index'])->name('custom_fields.index');

                //Members Documents
                Route::get('member_documents/{member_id}', [MemberDocumentController::class, 'index'])->name('member_documents.index');
                Route::get('member_documents/create/{member_id}', [MemberDocumentController::class, 'create'])->name('member_documents.create');
                Route::resource('member_documents', MemberDocumentController::class)->except(['index', 'create', 'show']);

                //Savings Accounts
                Route::get('savings_accounts/get_account_by_member_id/{member_id}', [SavingsAccountController::class, 'get_account_by_member_id']);
                Route::get('savings_accounts/get_table_data', [SavingsAccountController::class, 'get_table_data']);
                Route::resource('savings_accounts', SavingsAccountController::class)->middleware("demo:PUT|PATCH|DELETE");

                //Interest Controller
                Route::get('interest_calculation/get_last_posting/{account_type_id?}', [InterestController::class, 'get_last_posting'])->name('interest_calculation.get_last_posting');
                Route::match(['get', 'post'], 'interest_calculation/calculator', [InterestController::class, 'calculator'])->name('interest_calculation.calculator');
                Route::post('interest_calculation/posting', [InterestController::class, 'interest_posting'])->name('interest_calculation.interest_posting');

                //Transaction
                Route::get('transactions/get_table_data', [TransactionController::class, 'get_table_data']);
                Route::resource('transactions', TransactionController::class);


                //Get Transaction Categories
                Route::get('transaction_categories/get_category_by_type/{type}', [TransactionCategoryController::class, 'get_category_by_type']);

                //Deposit Requests
                Route::post('deposit_requests/get_table_data', [DepositRequestController::class, 'get_table_data']);
                Route::get('deposit_requests/approve/{id}', [DepositRequestController::class, 'approve'])->name('deposit_requests.approve');
                Route::get('deposit_requests/reject/{id}', [DepositRequestController::class, 'reject'])->name('deposit_requests.reject');
                Route::delete('deposit_requests/{id}', [DepositRequestController::class, 'destroy'])->name('deposit_requests.destroy');
                Route::get('deposit_requests/{id}', [DepositRequestController::class, 'show'])->name('deposit_requests.show');
                Route::get('deposit_requests', [DepositRequestController::class, 'index'])->name('deposit_requests.index');

                //Withdraw Requests
                Route::post('withdraw_requests/get_table_data', [WithdrawRequestController::class, 'get_table_data']);
                Route::get('withdraw_requests/approve/{id}', [WithdrawRequestController::class, 'approve'])->name('withdraw_requests.approve');
                Route::get('withdraw_requests/reject/{id}', [WithdrawRequestController::class, 'reject'])->name('withdraw_requests.reject');
                Route::delete('withdraw_requests/{id}', [WithdrawRequestController::class, 'destroy'])->name('withdraw_requests.destroy');
                Route::get('withdraw_requests/{id}', [WithdrawRequestController::class, 'show'])->name('withdraw_requests.show');
                Route::get('withdraw_requests', [WithdrawRequestController::class, 'index'])->name('withdraw_requests.index');

                //Expense
                Route::get('expenses/get_table_data', [ExpenseController::class, 'get_table_data']);
                Route::resource('expenses', ExpenseController::class);

                //Loan Controller
                Route::get('loans/upcoming_loan_repayments', [LoanController::class, 'upcoming_loan_repayments'])->name('loans.upcoming_loan_repayments');
                Route::post('loans/get_table_data', [LoanController::class, 'get_table_data']);
                Route::get('loans/calculator', [LoanController::class, 'calculator'])->name('loans.admin_calculator');
                Route::post('loans/calculator/calculate', [LoanController::class, 'calculate'])->name('loans.calculate');
                Route::match(['get', 'post'], 'loans/approve/{id}', [LoanController::class, 'approve'])->name('loans.approve');
                Route::get('loans/reject/{id}', [LoanController::class, 'reject'])->name('loans.reject');
                Route::get('loans/filter/{status?}', [LoanController::class, 'index'])->name('loans.filter')->where('status', '[A-Za-z]+');
                Route::resource('loans', LoanController::class);

                //Loan Collateral Controller
                Route::get('loan_collaterals/loan/{loan_id}', [LoanCollateralController::class, 'index'])->name('loan_collaterals.index');
                Route::resource('loan_collaterals', LoanCollateralController::class)->except('index');

                //Loan Guarantor Controller
                Route::resource('guarantors', GuarantorController::class)->except(['show', 'index']);
                
                //Guarantor Request Routes (public - no authentication required)
                Route::get('guarantor/invitation/{token}', [GuarantorController::class, 'showInvitation'])->name('guarantor.invitation');
                Route::post('guarantor/accept/{token}', [GuarantorController::class, 'accept'])->name('guarantor.accept');
                Route::get('guarantor/decline/{token}', [GuarantorController::class, 'decline'])->name('guarantor.decline');

                //Loan Payment Controller
                Route::get('loan_payments/get_repayment_by_loan_id/{loan_id}', [LoanPaymentController::class, 'get_repayment_by_loan_id']);
                Route::get('loan_payments/get_table_data', [LoanPaymentController::class, 'get_table_data']);
                Route::resource('loan_payments', LoanPaymentController::class);

                //Document Management Routes
                Route::get('documents/data', [App\Http\Controllers\DocumentController::class, 'getTableData'])->name('documents.data');
                Route::get('documents/stats', [App\Http\Controllers\DocumentController::class, 'getStats'])->name('documents.stats');
                Route::get('documents/{id}/download', [App\Http\Controllers\DocumentController::class, 'download'])->name('documents.download');
                Route::get('documents/{id}/view', [App\Http\Controllers\DocumentController::class, 'view'])->name('documents.view');
                Route::get('documents/category/{category}', [App\Http\Controllers\DocumentController::class, 'getByCategory'])->name('documents.category');
                Route::get('documents/latest/{category}', [App\Http\Controllers\DocumentController::class, 'getLatest'])->name('documents.latest');
                Route::resource('documents', App\Http\Controllers\DocumentController::class);


                //Bank Accounts
                Route::resource('bank_accounts', BankAccountController::class)->middleware("demo:PUT|PATCH|DELETE");
                
                //Payment Methods Management
                Route::resource('payment_methods', PaymentMethodController::class)->middleware("demo:PUT|PATCH|DELETE");
                Route::get('payment_methods/config/form', [PaymentMethodController::class, 'getConfigForm'])->name('payment_methods.config.form');
                Route::post('payment_methods/{id}/test', [PaymentMethodController::class, 'testConnection'])->name('payment_methods.test');
                
                //Withdrawal Request Management - with rate limiting
                Route::get('withdrawal_requests', [App\Http\Controllers\Admin\WithdrawalRequestController::class, 'index'])->name('admin.withdrawal_requests.index');
                Route::get('withdrawal_requests/{id}', [App\Http\Controllers\Admin\WithdrawalRequestController::class, 'show'])->name('admin.withdrawal_requests.show');
                Route::post('withdrawal_requests/{id}/approve', [App\Http\Controllers\Admin\WithdrawalRequestController::class, 'approve'])
                    ->name('admin.withdrawal_requests.approve')
                    ->middleware('throttle:admin-withdraw');
                Route::post('withdrawal_requests/{id}/reject', [App\Http\Controllers\Admin\WithdrawalRequestController::class, 'reject'])
                    ->name('admin.withdrawal_requests.reject')
                    ->middleware('throttle:admin-withdraw');
                Route::get('withdrawal_requests/statistics', [App\Http\Controllers\Admin\WithdrawalRequestController::class, 'statistics'])->name('admin.withdrawal_requests.statistics');

                //Bank Transaction
                Route::get('bank_transactions/get_table_data', [BankTransactionController::class, 'get_table_data']);
                Route::resource('bank_transactions', BankTransactionController::class)->middleware("demo:PUT|PATCH|DELETE");

                //Audit Trail
                Route::get('audit/get_table_data', [AuditController::class, 'getTableData'])->name('audit.get_table_data');
                Route::get('audit/statistics', [AuditController::class, 'statistics'])->name('audit.statistics');
                Route::get('audit/export', [AuditController::class, 'export'])->name('audit.export');
                Route::resource('audit', AuditController::class)->only(['index', 'show'])->names('audit');

                //VSLA Management Routes
                Route::prefix('vsla')->middleware('vsla.access')->group(function () {
                    //VSLA Settings
                    Route::get('settings', [App\Http\Controllers\VslaSettingsController::class, 'index'])->name('vsla.settings.index');
                    Route::post('settings', [App\Http\Controllers\VslaSettingsController::class, 'update'])->name('vsla.settings.update');
                    Route::post('settings/sync-accounts', [App\Http\Controllers\VslaSettingsController::class, 'syncMemberAccounts'])->name('vsla.settings.sync-accounts');
                    Route::post('settings/assign-role', [App\Http\Controllers\VslaSettingsController::class, 'assignRole'])->name('vsla.settings.assign-role');
                    Route::post('settings/remove-role', [App\Http\Controllers\VslaSettingsController::class, 'removeRole'])->name('vsla.settings.remove-role');
                    
                    //VSLA Cycles
                    Route::get('cycles', [App\Http\Controllers\VslaCycleController::class, 'index'])->name('vsla.cycles.index');
                    Route::get('cycles/create', [App\Http\Controllers\VslaCycleController::class, 'create'])->name('vsla.cycles.create');
                    Route::post('cycles', [App\Http\Controllers\VslaCycleController::class, 'store'])->name('vsla.cycles.store');
                    Route::get('cycles/admin-show/{id}', [App\Http\Controllers\VslaCycleController::class, 'show'])->where('id', '[0-9]+')->name('vsla.cycles.admin_show');
                    Route::get('cycles/get-table-data', [App\Http\Controllers\VslaCycleController::class, 'getTableData'])->name('vsla.cycles.get_table_data');
                    Route::post('cycles/{id}/update-totals', [App\Http\Controllers\VslaCycleController::class, 'updateTotals'])->name('vsla.cycles.update_totals');
                    Route::post('cycles/{id}/end-cycle', [App\Http\Controllers\VslaCycleController::class, 'endCycle'])->name('vsla.cycles.end_cycle');
                    
                    //VSLA Meetings
                    Route::get('meetings', [App\Http\Controllers\VslaMeetingsController::class, 'index'])->name('vsla.meetings.index');
                    Route::get('meetings/create', [App\Http\Controllers\VslaMeetingsController::class, 'create'])->name('vsla.meetings.create');
                    Route::post('meetings', [App\Http\Controllers\VslaMeetingsController::class, 'store'])->name('vsla.meetings.store');
                    Route::get('meetings/{id}', [App\Http\Controllers\VslaMeetingsController::class, 'show'])->name('vsla.meetings.show');
                    Route::get('meetings/{id}/edit', [App\Http\Controllers\VslaMeetingsController::class, 'edit'])->name('vsla.meetings.edit');
                    Route::put('meetings/{id}', [App\Http\Controllers\VslaMeetingsController::class, 'update'])->name('vsla.meetings.update');
                    Route::delete('meetings/{id}', [App\Http\Controllers\VslaMeetingsController::class, 'destroy'])->name('vsla.meetings.destroy');
                    Route::post('meetings/{id}/attendance', [App\Http\Controllers\VslaMeetingsController::class, 'recordAttendance'])->name('vsla.meetings.attendance');
                
                // Debug route for VSLA
                Route::get('vsla-debug', function() {
                    $tenant = app('tenant');
                    return response()->json([
                        'tenant_id' => $tenant->id,
                        'tenant_slug' => $tenant->slug,
                        'vsla_enabled' => $tenant->isVslaEnabled(),
                        'meetings_count' => $tenant->vslaMeetings()->count()
                    ]);
                })->name('vsla.debug');
                
                // List all tenants for debugging
                Route::get('tenants-list', function() {
                    $tenants = \App\Models\Tenant::select('id', 'slug', 'name', 'vsla_enabled', 'status')->get();
                    return response()->json($tenants);
                })->name('tenants.list');
                    
                    //VSLA Transactions
                    Route::get('transactions', [App\Http\Controllers\VslaTransactionsController::class, 'index'])->name('vsla.transactions.index');
                    Route::get('transactions/create', [App\Http\Controllers\VslaTransactionsController::class, 'create'])->name('vsla.transactions.create');
                    Route::post('transactions', [App\Http\Controllers\VslaTransactionsController::class, 'store'])->name('vsla.transactions.store');
                    Route::get('transactions/bulk-create', [App\Http\Controllers\VslaTransactionsController::class, 'bulkCreate'])->name('vsla.transactions.bulk_create');
                    Route::post('transactions/bulk-store', [App\Http\Controllers\VslaTransactionsController::class, 'bulkStore'])->name('vsla.transactions.bulk_store');
                    Route::get('transactions/get-members', [App\Http\Controllers\VslaTransactionsController::class, 'getMembersForBulk'])->name('vsla.transactions.get_members');
                    Route::post('transactions/{id}/approve', [App\Http\Controllers\VslaTransactionsController::class, 'approve'])->name('vsla.transactions.approve');
                    Route::post('transactions/{id}/reject', [App\Http\Controllers\VslaTransactionsController::class, 'reject'])->name('vsla.transactions.reject');
                    Route::get('transactions/history', [App\Http\Controllers\VslaTransactionsController::class, 'transactionHistory'])->name('vsla.transactions.history');
                    Route::get('transactions/{id}/edit', [App\Http\Controllers\VslaTransactionsController::class, 'edit'])->name('vsla.transactions.edit');
                    Route::put('transactions/{id}', [App\Http\Controllers\VslaTransactionsController::class, 'update'])->name('vsla.transactions.update');
                    Route::delete('transactions/{id}', [App\Http\Controllers\VslaTransactionsController::class, 'destroy'])->name('vsla.transactions.destroy');
                    
                    //VSLA Cycles Management (Admin)
                    Route::get('cycles', [App\Http\Controllers\VslaShareOutController::class, 'index'])->name('vsla.cycles.index');
                    Route::get('cycles/create', [App\Http\Controllers\VslaShareOutController::class, 'create'])->name('vsla.cycles.create');
                    Route::post('cycles', [App\Http\Controllers\VslaShareOutController::class, 'store'])->name('vsla.cycles.store');
                    Route::get('cycles/{id}', [App\Http\Controllers\VslaShareOutController::class, 'show'])->name('vsla.cycles.show');
                    Route::post('cycles/{id}/calculate', [App\Http\Controllers\VslaShareOutController::class, 'calculate'])->name('vsla.cycles.calculate');
                    Route::post('cycles/{id}/approve', [App\Http\Controllers\VslaShareOutController::class, 'approve'])->name('vsla.cycles.approve');
                    Route::post('cycles/{id}/process-payout', [App\Http\Controllers\VslaShareOutController::class, 'processPayout'])->name('vsla.cycles.process_payout');
                    Route::post('cycles/{id}/cancel', [App\Http\Controllers\VslaShareOutController::class, 'cancel'])->name('vsla.cycles.cancel');
                    Route::get('cycles/{id}/report', [App\Http\Controllers\VslaShareOutController::class, 'exportReport'])->name('vsla.cycles.export_report');
                });

                //Report Controller
                Route::match(['get', 'post'], 'reports/account_statement', [ReportController::class, 'account_statement'])->name('reports.account_statement');
                Route::match(['get', 'post'], 'reports/account_balances', [ReportController::class, 'account_balances'])->name('reports.account_balances');
                Route::match(['get', 'post'], 'reports/transactions_report', [ReportController::class, 'transactions_report'])->name('reports.transactions_report');
                Route::match(['get', 'post'], 'reports/loan_report', [ReportController::class, 'loan_report'])->name('reports.loan_report');
                Route::get('reports/loan_due_report', [ReportController::class, 'loan_due_report'])->name('reports.loan_due_report');
                Route::match(['get', 'post'], 'reports/loan_repayment_report', [ReportController::class, 'loan_repayment_report'])->name('reports.loan_repayment_report');
                Route::match(['get', 'post'], 'reports/expense_report', [ReportController::class, 'expense_report'])->name('reports.expense_report');
                Route::match(['get', 'post'], 'reports/cash_in_hand', [ReportController::class, 'cash_in_hand'])->name('reports.cash_in_hand');
                Route::match(['get', 'post'], 'reports/bank_transactions', [ReportController::class, 'bank_transactions'])->name('reports.bank_transactions');
                Route::get('reports/bank_balances', [ReportController::class, 'bank_balances'])->name('reports.bank_balances');
                Route::match(['get', 'post'], 'reports/revenue_report', [ReportController::class, 'revenue_report'])->name('reports.revenue_report');
                
                // ==================== NEW ANALYTICS AND CHARTS ROUTES ====================
                Route::get('reports/analytics/loan-released-chart', [ReportController::class, 'loan_released_chart'])->name('reports.analytics.loan_released_chart');
                Route::get('reports/analytics/loan-collections-chart', [ReportController::class, 'loan_collections_chart'])->name('reports.analytics.loan_collections_chart');
                Route::get('reports/analytics/collections-vs-due-chart', [ReportController::class, 'collections_vs_due_chart'])->name('reports.analytics.collections_vs_due_chart');
                Route::get('reports/analytics/collections-vs-released-chart', [ReportController::class, 'collections_vs_released_chart'])->name('reports.analytics.collections_vs_released_chart');
                Route::get('reports/analytics/outstanding-loans-summary', [ReportController::class, 'outstanding_loans_summary'])->name('reports.analytics.outstanding_loans_summary');
                Route::get('reports/analytics/due-vs-collections-breakdown', [ReportController::class, 'due_vs_collections_breakdown'])->name('reports.analytics.due_vs_collections_breakdown');
                Route::get('reports/analytics/loan-statistics-chart', [ReportController::class, 'loan_statistics_chart'])->name('reports.analytics.loan_statistics_chart');
                Route::get('reports/analytics/new-clients-chart', [ReportController::class, 'new_clients_chart'])->name('reports.analytics.new_clients_chart');
                Route::get('reports/analytics/loan-status-pie-chart', [ReportController::class, 'loan_status_pie_chart'])->name('reports.analytics.loan_status_pie_chart');
                Route::get('reports/analytics/borrower-gender-chart', [ReportController::class, 'borrower_gender_chart'])->name('reports.analytics.borrower_gender_chart');
                Route::get('reports/analytics/recovery-rate-analysis', [ReportController::class, 'recovery_rate_analysis'])->name('reports.analytics.recovery_rate_analysis');
                Route::get('reports/analytics/loan-tenure-analysis', [ReportController::class, 'loan_tenure_analysis'])->name('reports.analytics.loan_tenure_analysis');
                Route::get('reports/analytics/borrower-age-analysis', [ReportController::class, 'borrower_age_analysis'])->name('reports.analytics.borrower_age_analysis');
                
                // ==================== NEW REPORTS ROUTES ====================
                Route::match(['get', 'post'], 'reports/borrowers_report', [ReportController::class, 'borrowers_report'])->name('reports.borrowers_report');
                Route::match(['get', 'post'], 'reports/loan_arrears_aging_report', [ReportController::class, 'loan_arrears_aging_report'])->name('reports.loan_arrears_aging_report');
                Route::match(['get', 'post'], 'reports/collections_report', [ReportController::class, 'collections_report'])->name('reports.collections_report');
                Route::match(['get', 'post'], 'reports/disbursement_report', [ReportController::class, 'disbursement_report'])->name('reports.disbursement_report');
                Route::match(['get', 'post'], 'reports/fees_report', [ReportController::class, 'fees_report'])->name('reports.fees_report');
                Route::match(['get', 'post'], 'reports/loan_officer_report', [ReportController::class, 'loan_officer_report'])->name('reports.loan_officer_report');
                Route::match(['get', 'post'], 'reports/loan_products_report', [ReportController::class, 'loan_products_report'])->name('reports.loan_products_report');
                Route::match(['get', 'post'], 'reports/monthly_report', [ReportController::class, 'monthly_report'])->name('reports.monthly_report');
                Route::match(['get', 'post'], 'reports/outstanding_report', [ReportController::class, 'outstanding_report'])->name('reports.outstanding_report');
                Route::match(['get', 'post'], 'reports/portfolio_at_risk_report', [ReportController::class, 'portfolio_at_risk_report'])->name('reports.portfolio_at_risk_report');
                Route::match(['get', 'post'], 'reports/at_glance_report', [ReportController::class, 'at_glance_report'])->name('reports.at_glance_report');
                Route::match(['get', 'post'], 'reports/balance_sheet', [ReportController::class, 'balance_sheet'])->name('reports.balance_sheet');
                Route::match(['get', 'post'], 'reports/profit_loss_statement', [ReportController::class, 'profit_loss_statement'])->name('reports.profit_loss_statement');
                
                // ==================== EXPORT ROUTES ====================
                Route::get('reports/export/borrowers_report', [App\Http\Controllers\ExportController::class, 'export_borrowers_report'])->name('reports.export.borrowers_report');
                Route::get('reports/export/loan_arrears_aging_report', [App\Http\Controllers\ExportController::class, 'export_loan_arrears_aging_report'])->name('reports.export.loan_arrears_aging_report');
                Route::get('reports/export/collections_report', [App\Http\Controllers\ExportController::class, 'export_collections_report'])->name('reports.export.collections_report');
                Route::get('reports/export/disbursement_report', [App\Http\Controllers\ExportController::class, 'export_disbursement_report'])->name('reports.export.disbursement_report');
                Route::get('reports/export/fees_report', [App\Http\Controllers\ExportController::class, 'export_fees_report'])->name('reports.export.fees_report');
                Route::get('reports/export/outstanding_report', [App\Http\Controllers\ExportController::class, 'export_outstanding_report'])->name('reports.export.outstanding_report');
                Route::get('reports/export/portfolio_at_risk_report', [App\Http\Controllers\ExportController::class, 'export_portfolio_at_risk_report'])->name('reports.export.portfolio_at_risk_report');
                Route::get('reports/export/loan_officer_report', [App\Http\Controllers\ExportController::class, 'export_loan_officer_report'])->name('reports.export.loan_officer_report');
                Route::get('reports/export/loan_products_report', [App\Http\Controllers\ExportController::class, 'export_loan_products_report'])->name('reports.export.loan_products_report');
                
                // ==================== PRINT ROUTES ====================
                Route::get('print/repayment-receipt/{payment_id}', [App\Http\Controllers\PrintController::class, 'repayment_receipt'])->name('print.repayment_receipt');
                Route::get('print/loan-statement/{loan_id}', [App\Http\Controllers\PrintController::class, 'loan_statement'])->name('print.loan_statement');
                Route::get('print/borrower-statement/{member_id}', [App\Http\Controllers\PrintController::class, 'borrower_statement'])->name('print.borrower_statement');
                Route::get('print/loan-schedule/{loan_id}', [App\Http\Controllers\PrintController::class, 'loan_schedule'])->name('print.loan_schedule');
                Route::get('print/savings-statement/{account_id}', [App\Http\Controllers\PrintController::class, 'savings_statement'])->name('print.savings_statement');
                Route::get('print/savings-transaction-receipt/{transaction_id}', [App\Http\Controllers\PrintController::class, 'savings_transaction_receipt'])->name('print.savings_transaction_receipt');
                Route::get('print/other-income-receipt/{transaction_id}', [App\Http\Controllers\PrintController::class, 'other_income_receipt'])->name('print.other_income_receipt');
            });

            /** Tenant Customer Routes **/
            Route::middleware('tenant.customer')->prefix('portal')->group(function () {
                //Membership Details
                Route::get('profile/membership_details', [ProfileController::class, 'membership_details'])->name('profile.membership_details');

                //Transfer Controller
                Route::match(['get', 'post'], 'transfer/own_account_transfer', [App\Http\Controllers\Customer\TransferController::class, 'own_account_transfer'])->name('transfer.own_account_transfer');
                Route::match(['get', 'post'], 'transfer/other_account_transfer', [App\Http\Controllers\Customer\TransferController::class, 'other_account_transfer'])->name('transfer.other_account_transfer');
                Route::get('transfer/history', [App\Http\Controllers\Customer\TransferController::class, 'transferHistory'])->name('transfer.history');
                Route::get('transfer/details/{id}', [App\Http\Controllers\Customer\TransferController::class, 'transferDetails'])->name('transfer.details');
                Route::get('transfer/{id}/transaction_details', [App\Http\Controllers\Customer\TransferController::class, 'transaction_details'])->name('trasnactions.details');
                Route::get('transfer/get_exchange_amount/{from?}/{to?}/{amount?}', [App\Http\Controllers\Customer\TransferController::class, 'get_exchange_amount'])->name('transfer.get_exchange_amount');
                Route::post('transfer/get_final_amount', [App\Http\Controllers\Customer\TransferController::class, 'get_final_amount'])->name('transfer.get_final_amount');
                Route::get('transfer/pending_requests', [App\Http\Controllers\Customer\TransferController::class, 'pending_requests'])->name('trasnactions.pending_requests');


                //Loan Controller
                Route::match(['get', 'post'], 'loans/calculator', [App\Http\Controllers\Customer\LoanController::class, 'calculator'])->name('loans.calculator');
                Route::get('loans/loan_products', [App\Http\Controllers\Customer\LoanController::class, 'loan_products'])->name('loans.loan_products');
                Route::match(['get', 'post'], 'loans/apply_loan', [App\Http\Controllers\Customer\LoanController::class, 'apply_loan'])->name('loans.apply_loan');
                Route::get('loans/loan_details/{id}', [App\Http\Controllers\Customer\LoanController::class, 'loan_details'])->name('loans.loan_details');
                Route::match(['get', 'post'], 'loans/payment/{loan_id}', [App\Http\Controllers\Customer\LoanController::class, 'loan_payment'])->name('loans.loan_payment');
                Route::get('loans/my_loans', [App\Http\Controllers\Customer\LoanController::class, 'index'])->name('loans.my_loans');

                // Lease Requests (Customer)
                Route::get('lease-requests/my-requests', [App\Http\Controllers\LeaseRequestController::class, 'memberRequests'])->name('lease-requests.member.index');
                Route::get('lease-requests/my-requests/{leaseRequest}', [App\Http\Controllers\LeaseRequestController::class, 'memberShow'])->name('lease-requests.member.show');
                Route::get('lease-requests/create', [App\Http\Controllers\LeaseRequestController::class, 'create'])->name('lease-requests.member.create');
                Route::post('lease-requests', [App\Http\Controllers\LeaseRequestController::class, 'store'])->name('lease-requests.member.store');

                //Advanced Loan Application Routes (for authenticated members only)
                // Route::get('/loan-application', [App\Http\Controllers\PublicLoanApplicationController::class, 'showApplicationForm'])->name('loan_application.form');
                // Route::post('/loan-application', [App\Http\Controllers\PublicLoanApplicationController::class, 'storeApplication'])->name('loan_application.store');
                // Route::get('/loan-application/success/{id}', [App\Http\Controllers\PublicLoanApplicationController::class, 'showSuccess'])->name('loan_application.success');
                // Route::get('/loan-application/status', [App\Http\Controllers\PublicLoanApplicationController::class, 'showStatus'])->name('loan_application.status');
                // Route::get('/loan-application/products', [App\Http\Controllers\PublicLoanApplicationController::class, 'getLoanProducts'])->name('loan_application.products');
                // Route::get('/loan-application/products/{id}', [App\Http\Controllers\PublicLoanApplicationController::class, 'getLoanProductDetails'])->name('loan_application.product_details');

                //Deposit Money
                Route::match(['get', 'post'], 'deposit/manual_deposit/{id}', [App\Http\Controllers\Customer\DepositController::class, 'manual_deposit'])->name('deposit.manual_deposit');
                Route::get('deposit/offline_methods', [App\Http\Controllers\Customer\DepositController::class, 'manual_methods'])->name('deposit.manual_methods');

                //Instant Deposit
                Route::get('deposit/get_exchange_amount/{from?}/{to?}/{amount?}', [App\Http\Controllers\Customer\DepositController::class, 'get_exchange_amount'])->name('deposit.get_exchange_amount');
                Route::match(['get', 'post'], 'deposit/instant_deposit/{id}', [App\Http\Controllers\Customer\DepositController::class, 'automatic_deposit'])->name('deposit.automatic_deposit');
                Route::get('deposit/instant_methods', [App\Http\Controllers\Customer\DepositController::class, 'automatic_methods'])->name('deposit.automatic_methods');

                //Withdraw Money - with rate limiting
                Route::get('withdraw/offline_methods', [App\Http\Controllers\Customer\WithdrawController::class, 'manual_methods'])->name('withdraw.manual_methods');
                Route::match(['get', 'post'], 'withdraw/offline_withdraw/{id}/{otp?}', [App\Http\Controllers\Customer\WithdrawController::class, 'manual_withdraw'])
                    ->name('withdraw.manual_withdraw')
                    ->middleware('throttle:withdraw');
                Route::get('withdraw/history', [App\Http\Controllers\Customer\WithdrawController::class, 'withdrawalHistory'])->name('withdraw.history');
                Route::get('withdraw/requests', [App\Http\Controllers\Customer\WithdrawController::class, 'withdrawalRequests'])->name('withdraw.requests');
                Route::get('withdraw/request_details/{id}', [App\Http\Controllers\Customer\WithdrawController::class, 'withdrawalRequestDetails'])->name('withdraw.request_details');

                //Funds Transfer - REMOVED (replaced with bank account payment methods)

                //Report Controller
                Route::match(['get', 'post'], 'reports/account_statement', [App\Http\Controllers\Customer\ReportController::class, 'account_statement'])->name('customer_reports.account_statement');
                Route::match(['get', 'post'], 'reports/transactions_report', [App\Http\Controllers\Customer\ReportController::class, 'transactions_report'])->name('customer_reports.transactions_report');
                Route::match(['get', 'post'], 'reports/account_balances', [App\Http\Controllers\Customer\ReportController::class, 'account_balances'])->name('customer_reports.account_balances');

        //VSLA Cycles (Member Access)
        Route::get('vsla/cycles', [App\Http\Controllers\Customer\VslaCycleController::class, 'index'])->name('customer.vsla.cycle.index');
        Route::get('vsla/cycles/show/{cycle_id}', [App\Http\Controllers\Customer\VslaCycleController::class, 'show'])->where('cycle_id', '[0-9]+')->name('customer.vsla.cycle.show');
        Route::post('vsla/cycles/send-report/{cycle_id}', [App\Http\Controllers\Customer\VslaCycleController::class, 'sendCompleteCycleReport'])->where('cycle_id', '[0-9]+')->name('customer.vsla.cycle.send_report');
        Route::get('vsla/notifications/preferences', [App\Http\Controllers\Customer\VslaCycleController::class, 'getNotificationPreferences'])->name('customer.vsla.notifications.preferences');
        Route::post('vsla/notifications/preferences', [App\Http\Controllers\Customer\VslaCycleController::class, 'updateNotificationPreferences'])->name('customer.vsla.notifications.update');
                
                // Debug route to see what's being captured
                Route::get('vsla/cycles/debug/{id}', function($id) {
                    return response()->json([
                        'id' => $id,
                        'tenant' => app('tenant')->slug,
                        'url' => request()->url(),
                        'route_name' => request()->route()->getName()
                    ]);
                })->name('customer.vsla.cycle.debug');
                
                //VSLA Shareout (Member Access) - Legacy routes for backward compatibility
                Route::get('vsla/shareouts', [App\Http\Controllers\Customer\VslaShareoutController::class, 'index'])->name('customer.vsla.cycles.index');
                Route::get('vsla/shareouts/{cycle}', [App\Http\Controllers\Customer\VslaShareoutController::class, 'show'])->name('customer.vsla.cycles.show');

                //Member Audit Trail
                Route::get('audit/get_table_data', [MemberAuditController::class, 'getTableData'])->name('member.audit.get_table_data');
                Route::get('audit/summary', [MemberAuditController::class, 'summary'])->name('member.audit.summary');
                Route::resource('audit', MemberAuditController::class)->only(['index', 'show'])->names('member.audit');
            });

        });
    });

    Route::get('switch_language', function () {
        if (isset($_GET['language'])) {
            session(['language' => $_GET['language']]);
            return back();
        }
    })->name('switch_language');

    Route::post('switch_branch', [BranchController::class, 'switchBranch'])->name('switch_branch');
    Route::get('switch_branch_reset', function () {
        request()->session()->forget(['branch', 'branch_id']);
        return back();
    })->name('switch_branch_reset');

    Route::get('tenants/check-tenant-slug/{ignoreId?}', [TenantController::class, 'checkSlug'])->name('check-slug');

    //Frontend Website
    Route::get('/about', [WebsiteController::class, 'about']);
    Route::get('/features', [WebsiteController::class, 'features']);
    Route::get('/pricing', [WebsiteController::class, 'pricing']);
    Route::get('/faq', [WebsiteController::class, 'faq']);
    Route::get('/blogs/{slug?}', [WebsiteController::class, 'blogs']);
    Route::get('/contact', [WebsiteController::class, 'contact']);
    Route::get('/privacy-policy', [WebsiteController::class, 'privacy_policy']);
    Route::get('/terms-condition', [WebsiteController::class, 'terms_condition']);
    Route::post('/send_message', [WebsiteController::class, 'send_message']);
    Route::post('/post_comment', [WebsiteController::class, 'post_comment']);
    Route::post('/email_subscription', [WebsiteController::class, 'email_subscription']);

    if (env('APP_INSTALLED', true)) {
        // Moved to end of file to avoid conflicts with tenant routes
    } else {
        Route::get('/', function () {
            echo "Installation";
        });
    }
});

//Dashboard Widget
Route::get('dashboard/json_expense_by_category', [DashboardController::class, 'json_expense_by_category'])->middleware('auth');
Route::get('dashboard/json_deposit_withdraw_analytics/{currency_id?}', [DashboardController::class, 'json_deposit_withdraw_analytics'])->middleware('auth');
Route::get('dashboard/analytics_data', [DashboardController::class, 'analytics_data'])->name('dashboard.analytics_data')->middleware('auth');
Route::post('dashboard/clear_cache', [DashboardController::class, 'clearCache'])->middleware('auth');

Route::get('admin/dashboard/json_package_wise_subscription', [SuperAdminDashboardController::class, 'json_package_wise_subscription'])->middleware('auth');
Route::get('admin/dashboard/json_yearly_revenue', [SuperAdminDashboardController::class, 'json_yearly_revenue'])->middleware('auth');
Route::get('admin/dashboard/json_yearly_signup', [SuperAdminDashboardController::class, 'json_yearly_signup'])->middleware('auth');

// REMOVED: Debug routes for security - these were exposing sensitive system information
// If debug functionality is needed, use the secured /debug-users endpoint with proper authentication

// Security Dashboard Routes
Route::prefix('admin/security')->middleware(['auth', 'superadmin'])->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\SecurityDashboardController::class, 'index'])->name('admin.security.dashboard');
    Route::get('/metrics', [App\Http\Controllers\Admin\SecurityDashboardController::class, 'getMetrics'])->name('admin.security.metrics');
    Route::get('/threats', [App\Http\Controllers\Admin\SecurityDashboardController::class, 'getThreatDetails'])->name('admin.security.threats');
    Route::get('/analytics', [App\Http\Controllers\Admin\SecurityDashboardController::class, 'getAnalytics'])->name('admin.security.analytics');
    Route::post('/block-ip', [App\Http\Controllers\Admin\SecurityDashboardController::class, 'blockIP'])->name('admin.security.block-ip');
    Route::post('/unblock-ip', [App\Http\Controllers\Admin\SecurityDashboardController::class, 'unblockIP'])->name('admin.security.unblock-ip');
    Route::get('/config', [App\Http\Controllers\Admin\SecurityDashboardController::class, 'getSecurityConfig'])->name('admin.security.config');
    Route::get('/export-logs', [App\Http\Controllers\Admin\SecurityDashboardController::class, 'exportLogs'])->name('admin.security.export-logs');
    
    // Security Testing Interface
    Route::get('/testing', [App\Http\Controllers\SecurityDashboardTestController::class, 'index'])->name('security.testing');
    Route::post('/testing/run', [App\Http\Controllers\SecurityDashboardTestController::class, 'runTests'])->name('security.testing.run');
    Route::get('/testing/results', [App\Http\Controllers\SecurityDashboardTestController::class, 'getResults'])->name('security.testing.results');
    Route::get('/testing/standards', [App\Http\Controllers\SecurityDashboardTestController::class, 'getBankingStandards'])->name('security.testing.standards');
    Route::get('/testing/history', [App\Http\Controllers\SecurityDashboardTestController::class, 'getTestHistory'])->name('security.testing.history');
    Route::get('/testing/detail/{id}', [App\Http\Controllers\SecurityDashboardTestController::class, 'getTestDetail'])->name('security.testing.detail');
    Route::delete('/testing/delete/{id}', [App\Http\Controllers\SecurityDashboardTestController::class, 'deleteTestResult'])->name('security.testing.delete');
    
    // VSLA-specific test routes
    Route::get('/testing/vsla', [App\Http\Controllers\SecurityDashboardTestController::class, 'runVSLATestsOnly'])->name('security.testing.vsla');
    Route::post('/testing/vsla/run', [App\Http\Controllers\SecurityDashboardTestController::class, 'runVSLATestsOnly'])->name('security.testing.vsla.run');
});

//Subscription Payment
Route::prefix('subscription_callback')->group(function () {
    Route::get('paypal', [PayPalProcessController::class, 'callback'])->name('subscription_callback.PayPal');
    Route::post('stripe', [StripeProcessController::class, 'callback'])->name('subscription_callback.Stripe');
    Route::post('razorpay', [RazorpayProcessController::class, 'callback'])->name('subscription_callback.Razorpay');
    Route::get('paystack', [PaystackProcessController::class, 'callback'])->name('subscription_callback.Paystack');
    Route::get('flutterwave', [FlutterwaveProcessController::class, 'callback'])->name('subscription_callback.Flutterwave');
    Route::get('mollie', [MollieProcessController::class, 'callback'])->name('subscription_callback.Mollie');
    Route::match(['get', 'post'], 'instamojo', [InstamojoProcessController::class, 'callback'])->name('subscription_callback.Instamojo');
    Route::post('buni', [BuniProcessController::class, 'callback'])->name('subscription_callback.Buni');
    Route::post('buni/ipn', [BuniProcessController::class, 'ipn'])->name('subscription_callback.Buni.ipn');
    Route::post('offline_payment/{slug}', [OfflineProcessController::class, 'callback'])->name('subscription_callback.offline');
});

// SIMPLE TEST ROUTE - OUTSIDE ALL GROUPS
Route::get('test-esignature/{id}', function($id) {
    return "E-Signature test route works! ID: " . $id;
})->name('test.esignature');

    Route::prefix('{tenant}')->middleware(['tenant'])->group(function () {
        //Public E-Signature Routes (no authentication required) with rate limiting
        Route::prefix('esignature-public')->name('esignature.public.')->middleware(['esignature.rate.limit:5,10'])->group(function () {
            Route::get('sign/{token}', [App\Http\Controllers\PublicESignatureController::class, 'showSigningPage'])->name('sign');
            Route::post('sign/{token}', [App\Http\Controllers\PublicESignatureController::class, 'submitSignature'])->middleware(['esignature.rate.limit:3,5'])->name('submit');
            Route::get('success/{token}', [App\Http\Controllers\PublicESignatureController::class, 'success'])->name('success');
            Route::post('decline/{token}', [App\Http\Controllers\PublicESignatureController::class, 'decline'])->middleware(['esignature.rate.limit:2,5'])->name('decline');
            Route::get('declined/{token}', [App\Http\Controllers\PublicESignatureController::class, 'declined'])->name('declined');
            Route::get('download/{token}', [App\Http\Controllers\PublicESignatureController::class, 'downloadDocument'])->middleware(['esignature.rate.limit:5,10'])->name('download-document');
            Route::get('fields/{token}', [App\Http\Controllers\PublicESignatureController::class, 'getFields'])->middleware(['esignature.rate.limit:10,5'])->name('fields');
            Route::post('validate-field/{token}', [App\Http\Controllers\PublicESignatureController::class, 'validateField'])->middleware(['esignature.rate.limit:20,5'])->name('validate-field');
        });

        
        //Public Loan Application Routes (accessible to anyone with tenant context)
        // Note: These routes are now moved to authenticated section below
        
        Route::prefix('callback')->group(function () {
        //Fiat Currency
        Route::get('paypal', [App\Http\Controllers\Gateway\PayPal\ProcessController::class, 'callback'])->name('callback.PayPal')->middleware('auth');
        Route::post('stripe', [App\Http\Controllers\Gateway\Stripe\ProcessController::class, 'callback'])->name('callback.Stripe')->middleware('auth');
        Route::post('razorpay', [App\Http\Controllers\Gateway\Razorpay\ProcessController::class, 'callback'])->name('callback.Razorpay')->middleware('auth');
        Route::get('paystack', [App\Http\Controllers\Gateway\Paystack\ProcessController::class, 'callback'])->name('callback.Paystack')->middleware('auth');
        Route::get('flutterwave', [App\Http\Controllers\Gateway\Flutterwave\ProcessController::class, 'callback'])->name('callback.Flutterwave')->middleware('auth');
        Route::get('mollie', [App\Http\Controllers\Gateway\Mollie\ProcessController::class, 'callback'])->name('callback.Mollie')->middleware('auth');
        Route::match(['get', 'post'], 'instamojo', [App\Http\Controllers\Gateway\Instamojo\ProcessController::class, 'callback'])->name('callback.Instamojo');
        Route::post('buni', [App\Http\Controllers\Gateway\Buni\ProcessController::class, 'callback'])->name('callback.Buni')->middleware('auth');
        Route::post('buni/ipn', [App\Http\Controllers\Gateway\Buni\ProcessController::class, 'ipn'])->name('callback.Buni.ipn');

        //Crypto Currency
        Route::post('coinpayments', [App\Http\Controllers\Gateway\CoinPayments\ProcessController::class, 'callback'])->name('CoinPayments');
    });
    
    // API Routes - Tenant-specific
    Route::prefix('api')->group(function () {
        // Public API routes (no authentication required)
        Route::get('/health', function () {
            return response()->json([
                'status' => 'ok',
                'timestamp' => now(),
                'version' => '1.0.0',
                'tenant' => app('tenant')->slug
            ]);
        })->withoutMiddleware(\App\Http\Middleware\MilitaryGradeSecurity::class);
        
        Route::get('/test', function () {
            return response()->json([
                'message' => 'API is working!',
                'tenant' => app('tenant')->slug
            ]);
        })->withoutMiddleware(\App\Http\Middleware\MilitaryGradeSecurity::class);
        
        Route::get('/middleware-test', function () {
            $middleware = request()->route()->gatherMiddleware();
            return response()->json([
                'message' => 'Tenant API middleware test',
                'middleware' => $middleware,
                'tenant' => app('tenant')->slug,
                'timestamp' => now()
            ]);
        });
        
        // API Authentication routes (require web authentication)
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::prefix('auth')->group(function () {
                Route::post('/generate-tenant-credentials', [App\Http\Controllers\Api\AuthController::class, 'generateTenantCredentials']);
                Route::post('/generate-member-credentials', [App\Http\Controllers\Api\AuthController::class, 'generateMemberCredentials']);
                Route::get('/api-keys', [App\Http\Controllers\Api\AuthController::class, 'listApiKeys']);
                Route::get('/api-keys/{id}', [App\Http\Controllers\Api\AuthController::class, 'getApiKeyDetails']);
                Route::post('/api-keys/{id}/revoke', [App\Http\Controllers\Api\AuthController::class, 'revokeApiKey']);
                Route::post('/api-keys/{id}/regenerate-secret', [App\Http\Controllers\Api\AuthController::class, 'regenerateSecret']);
            });
        });
        
        // Protected API routes (require API authentication)
        Route::middleware(['api.auth:tenant'])->group(function () {
            
            // Member Management
            Route::prefix('members')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\MemberController::class, 'index']);
                Route::post('/', [App\Http\Controllers\Api\MemberController::class, 'store']);
                Route::get('/{id}', [App\Http\Controllers\Api\MemberController::class, 'show']);
                Route::get('/{id}/savings-accounts', [App\Http\Controllers\Api\MemberController::class, 'getSavingsAccounts']);
                Route::get('/{id}/loans', [App\Http\Controllers\Api\MemberController::class, 'getLoans']);
                Route::get('/{id}/transactions', [App\Http\Controllers\Api\MemberController::class, 'getTransactionHistory']);
                Route::get('/{id}/vsla-info', [App\Http\Controllers\Api\MemberController::class, 'getVslaInfo']);
            });

            // Payment Processing
            Route::prefix('payments')->group(function () {
                Route::post('/process', [App\Http\Controllers\Api\PaymentController::class, 'processPayment']);
                Route::post('/transfer', [App\Http\Controllers\Api\PaymentController::class, 'transferFunds']);
                Route::get('/history/{memberId}', [App\Http\Controllers\Api\PaymentController::class, 'getPaymentHistory']);
                Route::get('/balance/{accountId}', [App\Http\Controllers\Api\PaymentController::class, 'getAccountBalance']);
            });

            // Transaction Management
            Route::prefix('transactions')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\TransactionController::class, 'index']);
                Route::get('/{id}', [App\Http\Controllers\Api\TransactionController::class, 'show']);
                Route::get('/summary', [App\Http\Controllers\Api\TransactionController::class, 'getSummary']);
                Route::get('/bank-transactions', [App\Http\Controllers\Api\TransactionController::class, 'getBankTransactions']);
                Route::get('/vsla-transactions', [App\Http\Controllers\Api\TransactionController::class, 'getVslaTransactions']);
            });

            // Bank Account Management
            Route::prefix('bank-accounts')->group(function () {
                Route::get('/', function (Request $request) {
                    $accounts = \App\Models\BankAccount::where('tenant_id', app('tenant')->id)
                                                     ->with('currency')
                                                     ->get();
                    
                    return response()->json([
                        'success' => true,
                        'data' => $accounts->map(function ($account) {
                            return [
                                'id' => $account->id,
                                'account_name' => $account->account_name,
                                'account_number' => $account->account_number,
                                'bank_name' => $account->bank_name,
                                'current_balance' => $account->current_balance,
                                'available_balance' => $account->available_balance,
                                'currency' => $account->currency->name,
                                'is_active' => $account->is_active,
                            ];
                        })
                    ]);
                });
                
                Route::get('/{id}', function (Request $request, $id) {
                    $account = \App\Models\BankAccount::where('tenant_id', app('tenant')->id)
                                                    ->with('currency')
                                                    ->findOrFail($id);
                    
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'id' => $account->id,
                            'account_name' => $account->account_name,
                            'account_number' => $account->account_number,
                            'bank_name' => $account->bank_name,
                            'opening_balance' => $account->opening_balance,
                            'current_balance' => $account->current_balance,
                            'available_balance' => $account->available_balance,
                            'blocked_balance' => $account->blocked_balance,
                            'currency' => $account->currency->name,
                            'is_active' => $account->is_active,
                            'allow_negative_balance' => $account->allow_negative_balance,
                            'minimum_balance' => $account->minimum_balance,
                            'maximum_balance' => $account->maximum_balance,
                            'opening_date' => $account->opening_date,
                            'last_balance_update' => $account->last_balance_update,
                        ]
                    ]);
                });
            });

            // VSLA Management
            Route::prefix('vsla')->group(function () {
                Route::get('/meetings', function (Request $request) {
                    $meetings = \App\Models\VslaMeeting::where('tenant_id', app('tenant')->id)
                                                     ->with(['createdUser:id,name'])
                                                     ->orderBy('meeting_date', 'desc')
                                                     ->paginate($request->get('per_page', 20));
                    
                    return response()->json([
                        'success' => true,
                        'data' => $meetings->items(),
                        'pagination' => [
                            'current_page' => $meetings->currentPage(),
                            'last_page' => $meetings->lastPage(),
                            'per_page' => $meetings->perPage(),
                            'total' => $meetings->total(),
                        ]
                    ]);
                });
                
                Route::get('/settings', function (Request $request) {
                    $settings = \App\Models\VslaSetting::where('tenant_id', app('tenant')->id)
                                                     ->first();
                    
                    if (!$settings) {
                        return response()->json([
                            'error' => 'VSLA settings not found',
                            'message' => 'VSLA module is not configured for this organization'
                        ], 404);
                    }
                    
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'share_amount' => $settings->share_amount,
                            'penalty_amount' => $settings->penalty_amount,
                            'welfare_amount' => $settings->welfare_amount,
                            'meeting_frequency' => $settings->meeting_frequency,
                            'meeting_time' => $settings->getFormattedMeetingTime(),
                            'meeting_days' => $settings->getMeetingDaysString(),
                            'auto_approve_loans' => $settings->auto_approve_loans,
                            'max_loan_amount' => $settings->max_loan_amount,
                            'max_loan_duration_days' => $settings->max_loan_duration_days,
                        ]
                    ]);
                });
            });

            // Reports and Analytics
            Route::prefix('reports')->group(function () {
                Route::get('/financial-summary', function (Request $request) {
                    $tenantId = app('tenant')->id;
                    
                    $summary = [
                        'total_members' => \App\Models\Member::where('tenant_id', $tenantId)->count(),
                        'active_members' => \App\Models\Member::where('tenant_id', $tenantId)->where('status', 1)->count(),
                        'total_savings' => \App\Models\Transaction::where('tenant_id', $tenantId)
                                                                 ->where('dr_cr', 'cr')
                                                                 ->sum('amount'),
                        'total_loans' => \App\Models\Loan::where('tenant_id', $tenantId)->sum('applied_amount'),
                        'outstanding_loans' => \App\Models\Loan::where('tenant_id', $tenantId)
                                                              ->where('status', 2)
                                                              ->get()
                                                              ->sum(function ($loan) {
                                                                  return $loan->total_payable - $loan->total_paid;
                                                              }),
                        'bank_accounts' => \App\Models\BankAccount::where('tenant_id', $tenantId)->count(),
                        'total_bank_balance' => \App\Models\BankAccount::where('tenant_id', $tenantId)->sum('current_balance'),
                    ];
                    
                    return response()->json([
                        'success' => true,
                        'data' => $summary
                    ]);
                });
            });
        });
        
        // Member-specific API routes (require member API authentication)
        Route::middleware(['api.auth:member'])->group(function () {
            Route::prefix('member')->group(function () {
                Route::get('/profile', function (Request $request) {
                    $apiKey = $request->attributes->get('api_key');
                    $member = \App\Models\Member::where('tenant_id', $apiKey->tenant_id)
                                              ->where('id', $request->get('member_id'))
                                              ->firstOrFail();
                    
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'id' => $member->id,
                            'name' => $member->name,
                            'member_no' => $member->member_no,
                            'email' => $member->email,
                            'mobile' => $member->mobile,
                            'status' => $member->status,
                        ]
                    ]);
                });
                
                Route::get('/accounts', function (Request $request) {
                    $apiKey = $request->attributes->get('api_key');
                    $member = \App\Models\Member::where('tenant_id', $apiKey->tenant_id)
                                              ->where('id', $request->get('member_id'))
                                              ->firstOrFail();
                    
                    $accounts = \App\Models\SavingsAccount::where('member_id', $member->id)
                                                         ->where('tenant_id', $apiKey->tenant_id)
                                                         ->with(['savings_type:id,name'])
                                                         ->get();
                    
                    $accountsWithBalance = $accounts->map(function ($account) use ($member) {
                        $balance = get_account_balance($account->id, $member->id);
                        
                        return [
                            'id' => $account->id,
                            'account_number' => $account->account_number,
                            'savings_type' => $account->savings_type->name,
                            'balance' => $balance,
                            'status' => $account->status,
                        ];
                    });
                    
                    return response()->json([
                        'success' => true,
                        'data' => $accountsWithBalance
                    ]);
                });
                
                Route::get('/transactions', function (Request $request) {
                    $apiKey = $request->attributes->get('api_key');
                    $member = \App\Models\Member::where('tenant_id', $apiKey->tenant_id)
                                              ->where('id', $request->get('member_id'))
                                              ->firstOrFail();
                    
                    $query = \App\Models\Transaction::where('member_id', $member->id)
                                                  ->where('tenant_id', $apiKey->tenant_id);
                    
                    if ($request->has('account_id')) {
                        $query->where('savings_account_id', $request->account_id);
                    }
                    
                    if ($request->has('date_from')) {
                        $query->whereDate('trans_date', '>=', $request->date_from);
                    }
                    
                    if ($request->has('date_to')) {
                        $query->whereDate('trans_date', '<=', $request->date_to);
                    }
                    
                    $transactions = $query->with(['savingsAccount.savings_type:id,name'])
                                        ->orderBy('trans_date', 'desc')
                                        ->paginate($request->get('per_page', 20));
                    
                    return response()->json([
                        'success' => true,
                        'data' => $transactions->items(),
                        'pagination' => [
                            'current_page' => $transactions->currentPage(),
                            'last_page' => $transactions->lastPage(),
                            'per_page' => $transactions->perPage(),
                            'total' => $transactions->total(),
                        ]
                    ]);
                });
            });
        });
    });
});

//Buni Payment Initiation - moved to tenant group

//Buni Withdraw Routes - REMOVED (integrated into enhanced withdrawal)
//Buni Payment Initiation (kept for other uses)
Route::prefix('{tenant}')->middleware(['tenant'])->group(function () {
    Route::middleware(['auth'])->group(function () {
        //Buni Payment Initiation
        Route::post('gateway/buni/initiate', [App\Http\Controllers\Gateway\Buni\ProcessController::class, 'initiate'])->name('gateway.buni.initiate');
    });
});

//Membership Subscription
Route::get('membership/packages', [MembershipController::class, 'packages'])->name('membership.packages');
Route::post('membership/choose_package', [MembershipController::class, 'choose_package'])->name('membership.choose_package');
Route::get('membership/payment_gateways', [MembershipController::class, 'payment_gateways'])->name('membership.payment_gateways');
Route::get('membership/make_payment/{gateway}', [MembershipController::class, 'make_payment'])->name('membership.make_payment');

Route::prefix('login/{provider}')->group(function () {
    Route::get('/', [SocialController::class, 'redirect']);
    Route::get('/callback', [SocialController::class, 'callback']);
});

// Receipt QR Code Routes (moved to tenant context)

Route::prefix('install')->group(function () {
    Route::get('/', [InstallController::class, 'index']);
    Route::get('database', [InstallController::class, 'database']);
    Route::post('process_install', [InstallController::class, 'process_install']);
    Route::get('create_user', [InstallController::class, 'create_user']);
    Route::post('store_user', [InstallController::class, 'store_user']);
    Route::get('system_settings', [InstallController::class, 'system_settings']);
    Route::post('finish', [InstallController::class, 'final_touch']);
});

Route::get('system/update/{action?}', [UpdateController::class, 'index']);
Route::get('migration/update', [UpdateController::class, 'update_migration']);

// Test API route outside tenant group
Route::get('api-test', function () {
    return response()->json([
        'message' => 'API test endpoint working!',
        'timestamp' => now()
    ]);
});

// Test tenant API route without tenant middleware
Route::get('{tenant}/api/test-direct', function ($tenantSlug) {
    $tenant = \App\Models\Tenant::where('slug', $tenantSlug)->first();
    if (!$tenant) {
        return response()->json(['error' => 'Tenant not found'], 404);
    }
    
    app()->instance('tenant', $tenant);
    
    return response()->json([
        'message' => 'Direct tenant API test working!',
        'tenant' => $tenant->slug,
        'timestamp' => now()
    ]);
});

// Test API route with no middleware
Route::get('api-simple', function () {
    return response()->json([
        'message' => 'Simple API test working!',
        'timestamp' => now()
    ]);
})->withoutMiddleware(\App\Http\Middleware\MilitaryGradeSecurity::class);

// Test route to show middleware
Route::get('middleware-test', function () {
    $middleware = request()->route()->gatherMiddleware();
    return response()->json([
        'message' => 'Middleware test',
        'middleware' => $middleware,
        'timestamp' => now()
    ]);
});

// PWA Routes
Route::get('/manifest.json', [App\Http\Controllers\PWAController::class, 'manifest'])->name('pwa.manifest');
Route::get('/pwa/status', [App\Http\Controllers\PWAController::class, 'getStatus'])->name('pwa.status');
Route::get('/pwa/install-prompt', [App\Http\Controllers\PWAController::class, 'showInstallPrompt'])->name('pwa.install-prompt');
Route::get('/offline', function() {
    return response()->file(public_path('offline'));
})->name('pwa.offline');

// Push Notification Routes
Route::post('/push/register', [App\Http\Controllers\PushNotificationController::class, 'register'])->name('push.register');
Route::post('/push/unregister', [App\Http\Controllers\PushNotificationController::class, 'unregister'])->name('push.unregister');
Route::get('/push/status', [App\Http\Controllers\PushNotificationController::class, 'status'])->name('push.status');
Route::post('/push/test', [App\Http\Controllers\PushNotificationController::class, 'test'])->name('push.test');

// Catch-all route moved to end to avoid conflicts with tenant routes
if (env('APP_INSTALLED', true)) {
    Route::get('/{slug?}', [WebsiteController::class, 'index']);
}