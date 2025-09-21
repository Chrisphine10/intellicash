<?php
/**
 * Quick Fix for QR Code Table Missing Error
 * This script creates the missing qr_code_settings table
 */

// Database connection
$host = 'localhost';
$dbname = 'intellicash';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create qr_code_settings table
    $sql = "CREATE TABLE IF NOT EXISTS `qr_code_settings` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    
    // Add qr_code_enabled column to tenants table if it doesn't exist
    $pdo->exec("ALTER TABLE `tenants` ADD COLUMN IF NOT EXISTS `qr_code_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `vsla_enabled`");
    
    // Create default settings for existing tenants
    $pdo->exec("INSERT IGNORE INTO `qr_code_settings` (`tenant_id`, `enabled`, `ethereum_enabled`, `ethereum_network`, `qr_code_size`, `qr_code_error_correction`, `verification_cache_days`, `auto_generate_qr`, `include_blockchain_verification`, `created_at`, `updated_at`)
                SELECT `id`, 0, 0, 'mainnet', 200, 'H', 30, 1, 0, NOW(), NOW() FROM `tenants`");
    
    echo "SUCCESS: QR Code tables created successfully!";
    echo "<br><a href='intelliwealth/modules'>Go to Modules</a>";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
    echo "<br>Please check your database connection settings.";
}
?>
