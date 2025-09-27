<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        //Import Dummy Data
        DB::unprepared(file_get_contents('public/uploads/dummy_data.sql'));

        DB::beginTransaction();

        // Create Super Admin
        DB::table('users')->insert([
            'name'              => 'Super Admin',
            'email'             => 'admin@demo.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('123456'),
            'status'            => 1,
            'profile_picture'   => 'default.png',
            'user_type'         => 'superadmin',
        ]);

        //Create Tenant
        $tenantId = DB::table('tenants')->insertGetId([
            'slug'              => 'intelli-demo',
            'name'              => 'IntelliDemo',
            'membership_type'   => 'member',
            'package_id'        => 7,
            'subscription_date' => now(),
            'valid_to'          => date('Y-m-d', strtotime(now() . ' + 25 years')),
            'status'            => 1,
        ]);

        DB::table('users')->insert([
            'name'            => 'IntelliDemo Admin',
            'email'           => 'admin@intellidemo.com',
            'user_type'       => 'admin',
            'tenant_id'       => $tenantId,
            'tenant_owner'    => 1,
            'status'          => 1,
            'profile_picture' => 'default.png',
            'password'        => Hash::make('123456'),
            'email_verified_at' => now(),
        ]);

        DB::table('currency')->insert([
            [
                'full_name'     => 'Kenyan Shilling',
                'name'          => 'KES',
                'exchange_rate' => 1.000000,
                'base_currency' => 1,
                'status'        => 1,
                'tenant_id'     => $tenantId,
            ],
            [
                'full_name'     => 'United States Dollar',
                'name'          => 'USD',
                'exchange_rate' => 0.007500,
                'base_currency' => 0,
                'status'        => 1,
                'tenant_id'     => $tenantId,
            ],
        ]);

        DB::commit();

    }
}
