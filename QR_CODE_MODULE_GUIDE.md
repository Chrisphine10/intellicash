# QR Code Module Guide

## Overview
The QR Code module allows tenants to generate unique QR codes for transaction receipts that can be verified for authenticity. The QR codes contain transaction verification data and can optionally be integrated with Ethereum blockchain for additional security.

## Features
- **Unique QR Codes**: Each transaction gets a unique QR code based on transaction data
- **Verification System**: QR codes can be scanned to verify transaction authenticity
- **Ethereum Integration**: Optional blockchain integration for enhanced security
- **Tenant Management**: Each tenant can enable/disable the QR code module
- **Receipt Integration**: QR codes automatically appear on transaction receipts

## Module Configuration

### 1. Enable QR Code Module
1. Go to **Modules** in your tenant dashboard
2. Find **QR Code Module** in the list
3. Click **Configure**
4. Toggle **Enable QR Code Module** to **Yes**
5. Save settings

### 2. Basic Settings
- **Enable QR Code Module**: Master switch for the module
- **QR Code Size**: Size of generated QR codes (default: 200px)
- **Include Transaction Hash**: Include cryptographic hash in QR data
- **Include Verification URL**: Include verification URL in QR data

### 3. Ethereum Integration (Optional)
- **Enable Ethereum Integration**: Toggle for blockchain features
- **Ethereum RPC URL**: Your Ethereum node RPC endpoint
- **Contract Address**: Smart contract address for verification
- **Account Address**: Your Ethereum account address
- **Private Key**: Private key for signing transactions

## How It Works

### QR Code Generation
1. When a transaction is created, the system generates a unique QR code
2. QR code contains:
   - Transaction ID
   - Transaction hash (SHA-256)
   - Verification URL
   - Timestamp
   - Tenant information

### QR Code Data Structure
```json
{
  "transaction_id": 123,
  "hash": "a1b2c3d4e5f6...",
  "verification_url": "https://yourdomain.com/verify/abc123",
  "timestamp": "2024-01-15T10:30:00Z",
  "tenant": "your-tenant-slug"
}
```

### Verification Process
1. User scans QR code with any QR scanner
2. QR code contains verification URL
3. User visits verification URL
4. System displays transaction details for verification

## Usage

### For Administrators
1. **View Transactions**: QR codes automatically appear on transaction details pages
2. **Print Receipts**: QR codes are included in both regular and POS receipts
3. **Verify Transactions**: Use the verification system to confirm transaction authenticity

### For Members
1. **Transaction Receipts**: QR codes appear on all transaction receipts
2. **Verification**: Members can scan QR codes to verify their transactions
3. **Mobile Access**: QR codes work on mobile devices for easy verification

## Technical Implementation

### Files Modified
- `app/Services/ReceiptQrService.php` - QR code generation service
- `app/Http/Controllers/ReceiptVerificationController.php` - Verification controller
- `app/Models/QrCodeSettings.php` - QR code settings model
- `resources/views/backend/admin/transaction/view.blade.php` - Admin transaction view
- `resources/views/backend/customer/transfer/transaction-details.blade.php` - Customer transaction view

### Database Tables
- `qr_code_settings` - Stores QR code configuration per tenant
- `tenants` - Added `qr_code_enabled` column

### Routes
- `GET /{tenant}/receipt/verify/{token}` - Verify transaction by token
- `POST /{tenant}/receipt/verify-qr-data` - Verify QR code data
- `GET /{tenant}/receipt/qr-code/{transactionId}` - Get QR code for transaction

## Troubleshooting

### QR Code Not Displaying
1. Check if QR Code module is enabled for your tenant
2. Verify QR code settings are configured
3. Check browser console for JavaScript errors
4. Ensure transaction exists and is accessible

### Verification Not Working
1. Verify the verification URL is accessible
2. Check if the transaction token is valid
3. Ensure tenant context is properly set

### Ethereum Integration Issues
1. Verify RPC URL is accessible
2. Check contract address is correct
3. Ensure account has sufficient balance
4. Verify private key is correct

## Security Considerations

### QR Code Security
- Each QR code contains a unique cryptographic hash
- Hash is generated using SHA-256 algorithm
- Verification tokens are time-limited
- QR codes are tenant-specific

### Ethereum Security
- Private keys are encrypted in database
- Smart contracts should implement proper access controls
- Use secure RPC endpoints
- Regularly rotate private keys

## Best Practices

### QR Code Design
- Use appropriate size for your use case
- Test QR codes on different devices
- Ensure good contrast for scanning
- Include fallback text for accessibility

### Verification System
- Implement rate limiting for verification requests
- Log verification attempts for security
- Provide clear error messages
- Support mobile-friendly verification

### Ethereum Integration
- Use testnet for development
- Implement proper error handling
- Monitor gas costs
- Keep private keys secure

## Support

For technical support or questions about the QR Code module:
1. Check this guide first
2. Review error logs
3. Contact system administrator
4. Check module configuration

## Changelog

### Version 1.0
- Initial QR code module implementation
- Basic verification system
- Tenant management integration
- Receipt integration
- Optional Ethereum integration
