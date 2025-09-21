# QR Code Module Setup Instructions

## Issue: Table 'qr_code_settings' doesn't exist

The error occurs because the database tables for the QR Code module haven't been created yet. Here are the solutions:

## Solution 1: Run Migration Script (Recommended)

1. **Run the PHP migration script:**
   ```bash
   php run_qr_migration.php
   ```

2. **If PHP command is not available, run via web browser:**
   - Navigate to: `http://localhost/intellicash/run_qr_migration.php`
   - This will create the required database tables

## Solution 2: Manual SQL Execution

1. **Open your MySQL database management tool** (phpMyAdmin, MySQL Workbench, etc.)

2. **Run the SQL script:**
   - Open `create_qr_code_tables.sql`
   - Copy and paste the SQL commands
   - Execute the script

3. **Verify tables were created:**
   ```sql
   SHOW TABLES LIKE 'qr_code_settings';
   DESCRIBE qr_code_settings;
   ```

## Solution 3: Laravel Artisan (If Available)

1. **Run the migration:**
   ```bash
   php artisan migrate
   ```

2. **If migration files are not recognized:**
   ```bash
   php artisan migrate:status
   php artisan migrate:refresh
   ```

## Verification Steps

After running any of the above solutions:

1. **Check if tables exist:**
   ```sql
   SELECT COUNT(*) FROM qr_code_settings;
   ```

2. **Access the modules page:**
   - Go to: `http://localhost/intellicash/intelliwealth/modules`
   - You should see the QR Code module without errors

3. **Test module activation:**
   - Click "Enable" on the QR Code module
   - Click "Configure" to access settings

## Troubleshooting

### If you still get errors:

1. **Check database connection:**
   - Verify your database credentials in `.env`
   - Ensure the database exists

2. **Check table permissions:**
   - Ensure your database user has CREATE and ALTER permissions

3. **Check Laravel configuration:**
   - Verify `config/database.php` settings
   - Check if migrations are enabled

### Common Issues:

- **Permission denied**: Run as database administrator
- **Table already exists**: Drop and recreate tables
- **Foreign key errors**: Check if `tenants` table exists

## Files Created

The following files were created for the QR Code module:

- `database/migrations/2024_01_15_000000_create_qr_code_settings_table.php`
- `database/migrations/2024_01_15_000001_add_qr_code_enabled_to_tenants_table.php`
- `create_qr_code_tables.sql` - Manual SQL script
- `run_qr_migration.php` - PHP migration script

## Next Steps

Once the tables are created:

1. **Enable QR Code module** in the modules page
2. **Configure basic settings** (QR code size, error correction)
3. **Setup Ethereum integration** (optional)
4. **Test with sample transactions**

## Support

If you continue to have issues:

1. Check the Laravel logs: `storage/logs/laravel.log`
2. Verify database connection settings
3. Ensure all required files are in place
4. Contact technical support with error details
