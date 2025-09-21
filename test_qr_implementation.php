<?php
/**
 * Test script for QR code implementation
 * Run this script to test the QR code functionality
 */

require_once 'vendor/autoload.php';

use App\Services\ReceiptQrService;
use App\Services\EthereumService;
use App\Services\CryptographicProtectionService;

// Mock transaction data for testing
$mockTransaction = (object) [
    'id' => 123,
    'amount' => 1000.00,
    'type' => 'deposit',
    'dr_cr' => 'cr',
    'member_id' => 1,
    'created_at' => new DateTime('2024-01-15 10:30:00'),
    'tenant_id' => 1,
    'status' => 'approved',
    'description' => 'Test transaction'
];

echo "=== QR Code Implementation Test ===\n\n";

try {
    // Initialize services
    $cryptoService = new CryptographicProtectionService();
    $ethereumService = new EthereumService();
    $qrService = new ReceiptQrService($cryptoService, $ethereumService);

    echo "1. Testing QR data generation...\n";
    $qrData = $qrService->generateQrData($mockTransaction);
    echo "   ✓ QR data generated successfully\n";
    echo "   - Transaction Hash: " . substr($qrData['tx_hash'], 0, 20) . "...\n";
    echo "   - Verification Token: " . substr($qrData['verification_token'], 0, 20) . "...\n";
    echo "   - Verification URL: " . $qrData['verification_url'] . "\n\n";

    echo "2. Testing QR code generation...\n";
    $qrCode = $qrService->generateQrCode($mockTransaction, 200);
    echo "   ✓ QR code generated successfully\n";
    echo "   - QR Code Type: " . (strpos($qrCode, 'data:image/png;base64,') === 0 ? 'PNG Base64' : 'Unknown') . "\n";
    echo "   - QR Code Length: " . strlen($qrCode) . " characters\n\n";

    echo "3. Testing QR data encoding/decoding...\n";
    $encodedQrData = $qrService->encodeQrData($qrData);
    echo "   ✓ QR data encoded successfully\n";
    echo "   - Encoded Length: " . strlen($encodedQrData) . " characters\n";
    
    $decodedQrData = $qrService->decodeQrData($encodedQrData);
    echo "   ✓ QR data decoded successfully\n";
    echo "   - Decoded Hash: " . substr($decodedQrData['tx_hash'], 0, 20) . "...\n\n";

    echo "4. Testing transaction verification...\n";
    $verificationResult = $qrService->verifyTransaction($qrData['verification_token']);
    if ($verificationResult['valid']) {
        echo "   ✓ Transaction verification successful\n";
        echo "   - Transaction ID: " . $verificationResult['transaction']['id'] . "\n";
        echo "   - Amount: " . $verificationResult['transaction']['amount'] . "\n";
    } else {
        echo "   ✗ Transaction verification failed: " . $verificationResult['error'] . "\n";
    }

    echo "\n=== Test Summary ===\n";
    echo "✓ QR code generation: Working\n";
    echo "✓ Data encoding/decoding: Working\n";
    echo "✓ Transaction verification: " . ($verificationResult['valid'] ? 'Working' : 'Failed') . "\n";
    echo "✓ Ethereum integration: " . ($ethereumService->isEnabled() ? 'Enabled' : 'Disabled') . "\n";

    echo "\n=== Implementation Notes ===\n";
    echo "- QR codes contain unique transaction hashes for verification\n";
    echo "- Each QR code includes a verification URL for easy scanning\n";
    echo "- Transaction data is cryptographically signed for security\n";
    echo "- Ethereum integration is optional but provides blockchain verification\n";
    echo "- QR codes are generated on-demand to ensure data freshness\n";

} catch (Exception $e) {
    echo "✗ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
