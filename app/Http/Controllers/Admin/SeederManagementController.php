<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Database\Seeders\SubscriptionPackagesSeeder;
use Database\Seeders\SaasSeeder;
use Database\Seeders\AssetManagementSeeder;
use Database\Seeders\BankingSystemTestDataSeeder;
use Database\Seeders\UtilitySeeder;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\LandingPageSeeder;
use Database\Seeders\PackageAdvancedFeaturesSeeder;
use Database\Seeders\VotingSystemSeeder;
use Database\Seeders\LoanPermissionSeeder;
use Database\Seeders\BuniAutomaticGatewaySeeder;
use Database\Seeders\LegalTemplatesSeeder;
use Database\Seeders\KenyanLegalComplianceSeeder;
use Database\Seeders\LoanTermsAndPrivacySeeder;
use Database\Seeders\MultiCountryLegalTemplatesSeeder;
use Database\Seeders\TenantModuleSeeder;
use Exception;

class SeederManagementController extends Controller
{
    /**
     * Display the seeder management interface
     */
    public function index()
    {
        $availableSeeders = $this->getAvailableSeeders();
        $systemStatus = $this->getSystemStatus();
        
        return view('admin.seeder-management.index', compact('availableSeeders', 'systemStatus'));
    }

    /**
     * Get available seeders with their status
     */
    private function getAvailableSeeders()
    {
        return [
            [
                'name' => 'Subscription Packages',
                'class' => 'SubscriptionPackagesSeeder',
                'description' => 'Core subscription packages and pricing plans',
                'category' => 'Core System',
                'priority' => 1,
                'status' => $this->checkSeederStatus('packages'),
                'has_data' => $this->safeTableCount('packages') > 0,
                'data_count' => $this->safeTableCount('packages'),
            ],
            [
                'name' => 'Core Utilities',
                'class' => 'UtilitySeeder',
                'description' => 'System utilities and configuration data',
                'category' => 'Core System',
                'priority' => 2,
                'status' => $this->checkSeederStatus('utilities'),
                'has_data' => $this->safeTableCount('utilities') > 0,
                'data_count' => $this->safeTableCount('utilities'),
            ],
            [
                'name' => 'Email Templates',
                'class' => 'EmailTemplateSeeder',
                'description' => 'Email templates for all system modules',
                'category' => 'Communication',
                'priority' => 3,
                'status' => $this->checkSeederStatus('email_templates'),
                'has_data' => $this->safeTableCount('email_templates') > 0,
                'data_count' => $this->safeTableCount('email_templates'),
            ],
            [
                'name' => 'Landing Page Content',
                'class' => 'LandingPageSeeder',
                'description' => 'Landing page content and settings',
                'category' => 'Content',
                'priority' => 4,
                'status' => $this->checkSeederStatus('landing_page'),
                'has_data' => $this->safeTableCount('landing_page') > 0,
                'data_count' => $this->safeTableCount('landing_page'),
            ],
            [
                'name' => 'Payment Gateways',
                'class' => 'BuniAutomaticGatewaySeeder',
                'description' => 'Payment gateway configurations',
                'category' => 'Payment',
                'priority' => 5,
                'status' => $this->checkSeederStatus('payment_gateways'),
                'has_data' => $this->safeTableCount('payment_gateways') > 0,
                'data_count' => $this->safeTableCount('payment_gateways'),
            ],
            [
                'name' => 'Loan Permissions',
                'class' => 'LoanPermissionSeeder',
                'description' => 'Loan system permissions and settings',
                'category' => 'Loans',
                'priority' => 6,
                'status' => $this->checkSeederStatus('loan_permissions'),
                'has_data' => $this->safeTableCount('loan_permissions') > 0,
                'data_count' => $this->safeTableCount('loan_permissions'),
            ],
            [
                'name' => 'Voting System',
                'class' => 'VotingSystemSeeder',
                'description' => 'Voting system configuration and sample data',
                'category' => 'Modules',
                'priority' => 7,
                'status' => $this->checkSeederStatus('voting_system'),
                'has_data' => $this->safeTableCount('voting_elections') > 0,
                'data_count' => $this->safeTableCount('voting_elections'),
            ],
            [
                'name' => 'Asset Management',
                'class' => 'AssetManagementSeeder',
                'description' => 'Asset management categories and sample data',
                'category' => 'Modules',
                'priority' => 8,
                'status' => $this->checkSeederStatus('asset_categories'),
                'has_data' => $this->safeTableCount('asset_categories') > 0,
                'data_count' => $this->safeTableCount('asset_categories'),
            ],
            [
                'name' => 'Legal Templates',
                'class' => 'LegalTemplatesSeeder',
                'description' => 'Legal templates and compliance documents',
                'category' => 'Compliance',
                'priority' => 9,
                'status' => $this->checkSeederStatus('legal_templates'),
                'has_data' => $this->safeTableCount('legal_templates') > 0,
                'data_count' => $this->safeTableCount('legal_templates'),
            ],
            [
                'name' => 'Kenyan Legal Compliance',
                'class' => 'KenyanLegalComplianceSeeder',
                'description' => 'Kenyan legal compliance templates',
                'category' => 'Compliance',
                'priority' => 10,
                'status' => $this->checkSeederStatus('kenyan_legal_compliance'),
                'has_data' => $this->safeTableCount('kenyan_legal_compliance') > 0,
                'data_count' => $this->safeTableCount('kenyan_legal_compliance'),
            ],
            [
                'name' => 'Loan Terms and Privacy',
                'class' => 'LoanTermsAndPrivacySeeder',
                'description' => 'Loan terms and privacy policy templates',
                'category' => 'Compliance',
                'priority' => 11,
                'status' => $this->checkSeederStatus('loan_terms_privacy'),
                'has_data' => $this->safeTableCount('loan_terms_privacy') > 0,
                'data_count' => $this->safeTableCount('loan_terms_privacy'),
            ],
            [
                'name' => 'Multi-Country Legal Templates',
                'class' => 'MultiCountryLegalTemplatesSeeder',
                'description' => 'Legal templates for multiple countries',
                'category' => 'Compliance',
                'priority' => 12,
                'status' => $this->checkSeederStatus('multi_country_legal'),
                'has_data' => $this->safeTableCount('multi_country_legal') > 0,
                'data_count' => $this->safeTableCount('multi_country_legal'),
            ],
        ];
    }

    /**
     * Check seeder status based on table existence and data
     */
    private function checkSeederStatus($tableName)
    {
        try {
            if (!Schema::hasTable($tableName)) {
                return 'table_missing';
            }
            
            $count = DB::table($tableName)->count();
            
            if ($count === 0) {
                return 'empty';
            }
            
            return 'populated';
        } catch (Exception $e) {
            return 'error';
        }
    }

    /**
     * Safely get table count, returning 0 if table doesn't exist
     */
    private function safeTableCount($tableName)
    {
        try {
            if (!Schema::hasTable($tableName)) {
                return 0;
            }
            return DB::table($tableName)->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get system status information
     */
    private function getSystemStatus()
    {
        return [
            'total_tenants' => $this->safeTableCount('tenants'),
            'total_users' => $this->safeTableCount('users'),
            'total_packages' => $this->safeTableCount('packages'),
            'total_currencies' => $this->safeTableCount('currency'),
            'total_roles' => $this->safeTableCount('roles'),
            'database_size' => $this->getDatabaseSize(),
            'last_seeder_run' => $this->getLastSeederRun(),
        ];
    }

    /**
     * Get database size
     */
    private function getDatabaseSize()
    {
        try {
            $size = DB::select("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = ?", [config('database.connections.mysql.database')]);
            return $size[0]->size_mb ?? 'Unknown';
        } catch (Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get last seeder run information
     */
    private function getLastSeederRun()
    {
        // This would typically be stored in a seeder_logs table
        // For now, we'll return a placeholder
        return 'Not tracked';
    }

    /**
     * Run a specific seeder
     */
    public function runSeeder(Request $request)
    {
        Log::info('Seeder run request received', [
            'request_data' => $request->all(),
            'user_id' => auth()->id(),
            'user_type' => auth()->user()->user_type ?? 'unknown'
        ]);

        $request->validate([
            'seeder_class' => 'required|string',
            'clear_existing' => 'boolean',
        ]);

        $seederClass = $request->input('seeder_class');
        $clearExisting = $request->boolean('clear_existing');

        try {
            $startTime = now();
            
            Log::info("Starting seeder execution", [
                'seeder' => $seederClass,
                'clear_existing' => $clearExisting,
                'start_time' => $startTime
            ]);
            
            // Create seeder instance
            $seeder = $this->createSeederInstance($seederClass);
            
            if (!$seeder) {
                Log::warning("Seeder class not found", ['seeder_class' => $seederClass]);
                return response()->json([
                    'success' => false,
                    'message' => 'Seeder class not found: ' . $seederClass,
                ], 404);
            }

            Log::info("Seeder instance created successfully", ['seeder_class' => $seederClass]);

            // Clear existing data if requested
            if ($clearExisting) {
                Log::info("Clearing existing seeder data", ['seeder_class' => $seederClass]);
                $this->clearSeederData($seederClass);
            }

            // Run the seeder
            Log::info("Executing seeder", ['seeder_class' => $seederClass]);
            $seeder->run();
            
            $endTime = now();
            $duration = $startTime->diffInSeconds($endTime);

            Log::info("Seeder executed successfully", [
                'seeder' => $seederClass,
                'duration' => $duration,
                'cleared_existing' => $clearExisting,
                'end_time' => $endTime
            ]);

            return response()->json([
                'success' => true,
                'message' => "Seeder {$seederClass} run successfully in {$duration} seconds",
                'duration' => $duration,
            ]);

        } catch (Exception $e) {
            Log::error("Seeder execution failed", [
                'seeder' => $seederClass,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Seeder failed: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ], 500);
        }
    }

    /**
     * Run multiple seeders
     */
    public function runMultipleSeeders(Request $request)
    {
        $request->validate([
            'seeder_classes' => 'required|array',
            'seeder_classes.*' => 'string',
            'clear_existing' => 'boolean',
        ]);

        $seederClasses = $request->input('seeder_classes');
        $clearExisting = $request->input('clear_existing', false);
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($seederClasses as $seederClass) {
            try {
                $seeder = $this->createSeederInstance($seederClass);
                
                if (!$seeder) {
                    $results[] = [
                        'seeder' => $seederClass,
                        'success' => false,
                        'message' => 'Seeder class not found',
                    ];
                    $failureCount++;
                    continue;
                }

                if ($clearExisting) {
                    $this->clearSeederData($seederClass);
                }

                $seeder->run();
                
                $results[] = [
                    'seeder' => $seederClass,
                    'success' => true,
                    'message' => 'Completed successfully',
                ];
                $successCount++;

            } catch (Exception $e) {
                $results[] = [
                    'seeder' => $seederClass,
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
                $failureCount++;
            }
        }

        Log::info("Multiple seeders run", [
            'total' => count($seederClasses),
            'successful' => $successCount,
            'failed' => $failureCount,
            'results' => $results,
        ]);

        return response()->json([
            'success' => $successCount > 0,
            'message' => "Completed {$successCount} seeders successfully, {$failureCount} failed",
            'results' => $results,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
        ]);
    }

    /**
     * Run all core seeders
     */
    public function runAllCoreSeeders(Request $request)
    {
        $coreSeeders = [
            'SubscriptionPackagesSeeder',
            'UtilitySeeder',
            'EmailTemplateSeeder',
            'LandingPageSeeder',
            'BuniAutomaticGatewaySeeder',
            'LoanPermissionSeeder',
        ];

        $request->merge(['seeder_classes' => $coreSeeders]);
        
        return $this->runMultipleSeeders($request);
    }

    /**
     * Create seeder instance
     */
    private function createSeederInstance($seederClass)
    {
        $seederMap = $this->getSeederMap();
        $fullClass = $seederMap[$seederClass] ?? null;
        
        Log::info('Creating seeder instance', [
            'seeder_class' => $seederClass,
            'full_class' => $fullClass,
            'class_exists' => $fullClass ? class_exists($fullClass) : false
        ]);
        
        if ($fullClass && class_exists($fullClass)) {
            return new $fullClass();
        }

        Log::warning('Seeder class not found or does not exist', [
            'seeder_class' => $seederClass,
            'full_class' => $fullClass,
            'available_seeders' => array_keys($seederMap)
        ]);

        return null;
    }

    /**
     * Clear seeder data
     */
    private function clearSeederData($seederClass)
    {
        $tableMap = [
            'SubscriptionPackagesSeeder' => ['packages'],
            'UtilitySeeder' => ['utilities'],
            'EmailTemplateSeeder' => ['email_templates'],
            'LandingPageSeeder' => ['landing_page'],
            'BuniAutomaticGatewaySeeder' => ['payment_gateways'],
            'LoanPermissionSeeder' => ['loan_permissions'],
            'VotingSystemSeeder' => ['voting_elections', 'voting_candidates', 'voting_votes'],
            'AssetManagementSeeder' => ['asset_categories', 'assets'],
            'LegalTemplatesSeeder' => ['legal_templates'],
            'KenyanLegalComplianceSeeder' => ['kenyan_legal_compliance'],
            'LoanTermsAndPrivacySeeder' => ['loan_terms_privacy'],
            'MultiCountryLegalTemplatesSeeder' => ['multi_country_legal'],
        ];

        $tables = $tableMap[$seederClass] ?? [];
        
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }
    }

    /**
     * Get seeder status details
     */
    public function getSeederStatus(Request $request)
    {
        Log::info('Seeder status request received', [
            'request_data' => $request->all(),
            'user_id' => auth()->id(),
            'user_type' => auth()->user()->user_type ?? 'unknown'
        ]);

        $seederClass = $request->input('seeder_class');
        
        if (!$seederClass) {
            Log::warning('Seeder status request missing seeder_class');
            return response()->json([
                'error' => 'Seeder class required',
                'available_seeders' => array_keys($this->getSeederMap())
            ], 400);
        }

        try {
            $seederInfo = $this->getSeederInfo($seederClass);
            Log::info('Seeder status retrieved successfully', ['seeder_class' => $seederClass]);
            return response()->json($seederInfo);
        } catch (Exception $e) {
            Log::error('Seeder status request failed', [
                'seeder_class' => $seederClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to get seeder status: ' . $e->getMessage(),
                'seeder_class' => $seederClass
            ], 500);
        }
    }

    /**
     * Get seeder map for debugging
     */
    private function getSeederMap()
    {
        return [
            'SubscriptionPackagesSeeder' => SubscriptionPackagesSeeder::class,
            'SaasSeeder' => SaasSeeder::class,
            'AssetManagementSeeder' => AssetManagementSeeder::class,
            'BankingSystemTestDataSeeder' => BankingSystemTestDataSeeder::class,
            'UtilitySeeder' => UtilitySeeder::class,
            'EmailTemplateSeeder' => EmailTemplateSeeder::class,
            'LandingPageSeeder' => LandingPageSeeder::class,
            'PackageAdvancedFeaturesSeeder' => PackageAdvancedFeaturesSeeder::class,
            'VotingSystemSeeder' => VotingSystemSeeder::class,
            'LoanPermissionSeeder' => LoanPermissionSeeder::class,
            'BuniAutomaticGatewaySeeder' => BuniAutomaticGatewaySeeder::class,
            'LegalTemplatesSeeder' => LegalTemplatesSeeder::class,
            'KenyanLegalComplianceSeeder' => KenyanLegalComplianceSeeder::class,
            'LoanTermsAndPrivacySeeder' => LoanTermsAndPrivacySeeder::class,
            'MultiCountryLegalTemplatesSeeder' => MultiCountryLegalTemplatesSeeder::class,
            'TenantModuleSeeder' => TenantModuleSeeder::class,
        ];
    }

    /**
     * Get detailed seeder information
     */
    private function getSeederInfo($seederClass)
    {
        $tableMap = [
            'SubscriptionPackagesSeeder' => ['packages'],
            'UtilitySeeder' => ['utilities'],
            'EmailTemplateSeeder' => ['email_templates'],
            'LandingPageSeeder' => ['landing_page'],
            'BuniAutomaticGatewaySeeder' => ['payment_gateways'],
            'LoanPermissionSeeder' => ['loan_permissions'],
            'VotingSystemSeeder' => ['voting_elections', 'voting_candidates', 'voting_votes'],
            'AssetManagementSeeder' => ['asset_categories', 'assets'],
            'LegalTemplatesSeeder' => ['legal_templates'],
            'KenyanLegalComplianceSeeder' => ['kenyan_legal_compliance'],
            'LoanTermsAndPrivacySeeder' => ['loan_terms_privacy'],
            'MultiCountryLegalTemplatesSeeder' => ['multi_country_legal'],
        ];

        $tables = $tableMap[$seederClass] ?? [];
        $tableInfo = [];

        foreach ($tables as $table) {
            $tableInfo[$table] = [
                'exists' => Schema::hasTable($table),
                'count' => $this->safeTableCount($table),
                'last_updated' => Schema::hasTable($table) ? DB::table($table)->max('updated_at') : null,
            ];
        }

        return [
            'seeder' => $seederClass,
            'tables' => $tableInfo,
            'total_records' => array_sum(array_column($tableInfo, 'count')),
        ];
    }
}
