-- IMMEDIATE FIX: Run this SQL script in your MySQL database
-- This will create the required tables for the QR Code module

-- Create qr_code_settings table
CREATE TABLE IF NOT EXISTS `qr_code_settings` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `tenant_id` bigint(20) unsigned NOT NULL,
    `enabled` tinyint(1) NOT NULL DEFAULT 0,
    `ethereum_enabled` tinyint(1) NOT NULL DEFAULT 0,
    `ethereum_network` varchar(50) NOT NULL DEFAULT 'mainnet',
    `ethereum_rpc_url` varchar(255) DEFAULT NULL,
    `ethereum_contract_address` varchar(42) DEFAULT NULL,
    `ethereum_account_address` varchar(42) DEFAULT NULL,
    `ethereum_private_key` text DEFAULT NULL,
    `qr_code_size` int(11) NOT NULL DEFAULT 200,
    `qr_code_error_correction` varchar(1) NOT NULL DEFAULT 'H',
    `verification_cache_days` int(11) NOT NULL DEFAULT 30,
    `auto_generate_qr` tinyint(1) NOT NULL DEFAULT 1,
    `include_blockchain_verification` tinyint(1) NOT NULL DEFAULT 0,
    `custom_settings` json DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `qr_code_settings_tenant_id_unique` (`tenant_id`),
    KEY `qr_code_settings_tenant_id_foreign` (`tenant_id`),
    CONSTRAINT `qr_code_settings_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add qr_code_enabled column to tenants table if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'tenants' 
     AND column_name = 'qr_code_enabled' 
     AND table_schema = DATABASE()) > 0,
    'SELECT "Column qr_code_enabled already exists" as message;',
    'ALTER TABLE `tenants` ADD COLUMN `qr_code_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `vsla_enabled`;'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Insert default QR code settings for existing tenants
INSERT IGNORE INTO `qr_code_settings` (`tenant_id`, `enabled`, `ethereum_enabled`, `ethereum_network`, `qr_code_size`, `qr_code_error_correction`, `verification_cache_days`, `auto_generate_qr`, `include_blockchain_verification`, `created_at`, `updated_at`)
SELECT 
    `id` as `tenant_id`,
    0 as `enabled`,
    0 as `ethereum_enabled`,
    'mainnet' as `ethereum_network`,
    200 as `qr_code_size`,
    'H' as `qr_code_error_correction`,
    30 as `verification_cache_days`,
    1 as `auto_generate_qr`,
    0 as `include_blockchain_verification`,
    NOW() as `created_at`,
    NOW() as `updated_at`
FROM `tenants` 
WHERE `id` NOT IN (SELECT COALESCE(`tenant_id`, 0) FROM `qr_code_settings`);

-- Verify tables were created
SELECT 'QR Code module tables created successfully!' as message;
SELECT COUNT(*) as tenant_count FROM `tenants`;
SELECT COUNT(*) as qr_settings_count FROM `qr_code_settings`;
