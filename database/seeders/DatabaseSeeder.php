<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Starting database seeding process...');
        
        // Core system seeders (run first - these don't require tenants)
        $this->command->info('Seeding core system data...');
        $this->call([
            UtilitySeeder::class,
            EmailTemplateSeeder::class,
            LandingPageSeeder::class,
        ]);

        // Payment gateway seeders
        $this->command->info('Seeding payment gateways...');
        $this->call([
            BuniAutomaticGatewaySeeder::class,
        ]);

        // Loan system seeders
        $this->command->info('Seeding loan system data...');
        $this->call([
            LoanPermissionSeeder::class,
        ]);

        // Voting system seeders (if needed)
        $this->command->info('Seeding voting system data...');
        $this->call([
            VotingSystemSeeder::class,
        ]);

        // Asset management seeders
        $this->command->info('Seeding asset management data...');
        $this->call([
            AssetManagementSeeder::class,
        ]);

        // Banking system test data (for demo purposes)
        $this->command->info('Seeding banking system test data...');
        $this->call([
            BankingSystemTestDataSeeder::class,
        ]);

        // Lease request test data
        $this->command->info('Seeding lease request test data...');
        $this->call([
            LeaseRequestTestSeeder::class,
        ]);

        // Voting security test data
        $this->command->info('Seeding voting security test data...');
        $this->call([
            VotingSecurityTestSeeder::class,
        ]);

        $this->command->info('Database seeding completed successfully!');
        $this->command->info('All default data has been seeded including:');
        $this->command->info('- Core system settings and utilities');
        $this->command->info('- Email templates for all modules');
        $this->command->info('- Landing page content and settings');
        $this->command->info('- Payment gateways and automatic gateways');
        $this->command->info('- Loan permissions and terms');
        $this->command->info('- Voting system sample data');
        $this->command->info('- Asset management data');
        $this->command->info('- Banking system test data');
        $this->command->info('- Lease request test data');
        $this->command->info('- Voting security test data');
    }
}
