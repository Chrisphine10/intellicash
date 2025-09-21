<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;

class DebugUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug users and tenant information';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== DEBUGGING USERS AND TENANTS ===');
        
        // Show all tenants
        $this->info('TENANTS:');
        $tenants = Tenant::all();
        foreach ($tenants as $tenant) {
            $this->line("- {$tenant->name} (ID: {$tenant->id}, Slug: {$tenant->slug}, Status: {$tenant->status})");
        }
        
        $this->newLine();
        
        // Show all users
        $this->info('USERS:');
        $users = User::all();
        foreach ($users as $user) {
            $this->line("- {$user->name} ({$user->email}) - Type: {$user->user_type} - Tenant: {$user->tenant_id} - Status: {$user->status}");
        }
        
        $this->newLine();
        
        // Show all roles
        $this->info('ROLES:');
        $roles = Role::all();
        foreach ($roles as $role) {
            $this->line("- {$role->name} (ID: {$role->id}) - Tenant: {$role->tenant_id}");
        }
        
        $this->newLine();
        
        // Show staff users specifically
        $this->info('STAFF USERS (admin/user types):');
        $staffUsers = User::where('user_type', 'admin')->orWhere('user_type', 'user')->get();
        foreach ($staffUsers as $user) {
            $this->line("- {$user->name} ({$user->email}) - Type: {$user->user_type} - Tenant: {$user->tenant_id}");
        }
        
        return 0;
    }
}
