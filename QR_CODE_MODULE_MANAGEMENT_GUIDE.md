# QR Code Module Management Guide

## Overview

This guide explains how to manage the QR Code module in IntelliCash, including activation, configuration, and network setup. The QR Code module provides secure transaction verification through QR codes with optional Ethereum blockchain integration.

## Module Management Interface

### Accessing Module Management

1. Navigate to: `http://localhost/intellicash/{tenant}/modules`
2. Login as an administrator
3. View all available modules including the QR Code module

### Module Status Indicators

- **Disabled**: Module is not active
- **Needs Configuration**: Module is enabled but requires setup
- **Active**: Module is fully configured and operational
- **Blockchain**: Indicates Ethereum integration is enabled

## QR Code Module Configuration

### Basic Settings

#### Enable QR Code Module
- Toggle to enable/disable QR code generation for receipts
- When enabled, QR codes will appear on all transaction receipts

#### Auto Generate QR Codes
- Automatically generate QR codes for new transactions
- Recommended for seamless user experience

### QR Code Settings

#### QR Code Size
- **Range**: 100-500 pixels
- **Default**: 200 pixels
- **Recommendation**: 200-300 pixels for optimal scanning

#### Error Correction Level
- **Low (L)**: ~7% error correction
- **Medium (M)**: ~15% error correction
- **Quartile (Q)**: ~25% error correction
- **High (H)**: ~30% error correction (recommended)

#### Verification Cache Days
- **Range**: 1-365 days
- **Default**: 30 days
- **Purpose**: How long verification data is cached

## Ethereum Blockchain Integration

### Supported Networks

| Network | Chain ID | Type | RPC URL Example |
|---------|----------|------|-----------------|
| Ethereum Mainnet | 1 | Production | `https://mainnet.infura.io/v3/your-project-id` |
| Goerli Testnet | 5 | Test | `https://goerli.infura.io/v3/your-project-id` |
| Sepolia Testnet | 11155111 | Test | `https://sepolia.infura.io/v3/your-project-id` |
| Polygon Mainnet | 137 | Production | `https://polygon-mainnet.infura.io/v3/your-project-id` |
| Polygon Mumbai | 80001 | Test | `https://polygon-mumbai.infura.io/v3/your-project-id` |

### Configuration Steps

1. **Enable Ethereum Integration**
   - Toggle "Enable Ethereum Integration"
   - This enables blockchain storage of transaction hashes

2. **Select Network**
   - Choose appropriate network (testnet for testing, mainnet for production)
   - Network information will be displayed automatically

3. **Configure RPC URL**
   - Get RPC URL from Infura, Alchemy, or other providers
   - Format: `https://{network}.infura.io/v3/{your-project-id}`

4. **Set Contract Address**
   - Deploy the IntelliCash smart contract
   - Enter the contract address (0x...)

5. **Configure Account**
   - Enter Ethereum account address (0x...)
   - Enter private key (will be encrypted)

6. **Test Connection**
   - Click "Test Connection" to verify setup
   - Ensure all fields are filled before testing

### Security Considerations

- **Private Key Encryption**: Private keys are encrypted before storage
- **Network Security**: Use HTTPS RPC URLs only
- **Account Security**: Use dedicated accounts for the application
- **Testnet First**: Always test on testnet before mainnet

## Module Activation Process

### Step 1: Enable Module
1. Go to Modules page
2. Click "Enable" on QR Code module
3. Module will be activated with default settings

### Step 2: Configure Settings
1. Click "Configure" button
2. Set basic QR code preferences
3. Save configuration

### Step 3: Setup Blockchain (Optional)
1. Enable Ethereum integration
2. Configure network settings
3. Test connection
4. Save configuration

### Step 4: Verify Setup
1. Create a test transaction
2. View receipt to confirm QR code appears
3. Scan QR code to test verification

## Troubleshooting

### Common Issues

#### QR Code Not Appearing
- **Check**: Module is enabled
- **Check**: Auto-generate is enabled
- **Check**: Transaction status is approved
- **Solution**: Verify module configuration

#### Ethereum Connection Failed
- **Check**: RPC URL is correct and accessible
- **Check**: Network selection matches RPC URL
- **Check**: Account has sufficient balance for gas
- **Solution**: Test connection and verify settings

#### Verification Failed
- **Check**: Verification token is valid
- **Check**: Transaction exists in database
- **Check**: Cache hasn't expired
- **Solution**: Check logs for specific errors

### Debug Mode

Enable debug logging in configuration:
```php
'log_level' => 'debug',
'log_transactions' => true,
```

### Log Files

Check these log files for issues:
- `storage/logs/laravel.log` - General application logs
- `storage/logs/ethereum.log` - Ethereum-specific logs
- `storage/logs/qr-code.log` - QR code generation logs

## API Endpoints

### Module Management
- `GET /modules` - List all modules
- `POST /modules/toggle-qr-code` - Toggle QR code module
- `GET /modules/qr-code/configure` - Configuration page
- `POST /modules/qr-code/update` - Update configuration
- `POST /modules/qr-code/test-ethereum` - Test Ethereum connection

### QR Code Generation
- `GET /receipt/qr-code/{transactionId}` - Get QR code for transaction
- `GET /receipt/verify/{token}` - Verify transaction
- `POST /receipt/verify-qr-data` - Verify using QR data

## Database Schema

### qr_code_settings Table
```sql
CREATE TABLE qr_code_settings (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    enabled BOOLEAN DEFAULT FALSE,
    ethereum_enabled BOOLEAN DEFAULT FALSE,
    ethereum_network VARCHAR(50) DEFAULT 'mainnet',
    ethereum_rpc_url VARCHAR(255),
    ethereum_contract_address VARCHAR(42),
    ethereum_account_address VARCHAR(42),
    ethereum_private_key TEXT,
    qr_code_size INTEGER DEFAULT 200,
    qr_code_error_correction VARCHAR(1) DEFAULT 'H',
    verification_cache_days INTEGER DEFAULT 30,
    auto_generate_qr BOOLEAN DEFAULT TRUE,
    include_blockchain_verification BOOLEAN DEFAULT FALSE,
    custom_settings JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
```

### tenants Table Addition
```sql
ALTER TABLE tenants ADD COLUMN qr_code_enabled BOOLEAN DEFAULT FALSE;
```

## Best Practices

### Security
1. Use testnet for development and testing
2. Encrypt private keys before storage
3. Use dedicated Ethereum accounts
4. Regularly rotate private keys
5. Monitor blockchain transactions

### Performance
1. Set appropriate cache duration
2. Use appropriate QR code size
3. Monitor RPC usage and costs
4. Implement rate limiting for API calls

### Maintenance
1. Regularly update smart contracts
2. Monitor network status
3. Backup configuration settings
4. Test after updates

## Support

For technical support:
1. Check troubleshooting section
2. Review log files
3. Test on testnet first
4. Contact development team

## Changelog

### Version 1.0.0
- Initial QR Code module implementation
- Basic QR code generation
- Ethereum integration
- Module management interface
- Multi-tenant support
