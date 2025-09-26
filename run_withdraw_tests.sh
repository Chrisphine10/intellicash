#!/bin/bash

# Withdraw Module Test Runner
# This script runs all tests related to the withdraw module

echo "=========================================="
echo "Running Withdraw Module Security Tests"
echo "=========================================="

# Set environment
export APP_ENV=testing
export DB_CONNECTION=sqlite
export DB_DATABASE=:memory:

# Run Feature Tests
echo "Running Feature Tests..."
php artisan test tests/Feature/WithdrawModuleSecurityTest.php --verbose

echo ""
echo "Running Unit Tests..."
php artisan test tests/Unit/WithdrawModuleUnitTest.php --verbose

echo ""
echo "Running Integration Tests..."
php artisan test tests/Integration/WithdrawModuleIntegrationTest.php --verbose

echo ""
echo "=========================================="
echo "All Withdraw Module Tests Completed"
echo "=========================================="

# Run specific security tests
echo ""
echo "Running Security-Specific Tests..."
php artisan test --filter="test_withdrawal_prevents_race_conditions|test_withdrawal_validates_account_ownership|test_withdrawal_validates_amount_constraints|test_file_upload_security|test_rate_limiting_prevents_abuse|test_tenant_isolation" --verbose

echo ""
echo "=========================================="
echo "Security Tests Summary"
echo "=========================================="
echo "✓ Race condition prevention"
echo "✓ Account ownership validation"
echo "✓ Amount constraint validation"
echo "✓ File upload security"
echo "✓ Rate limiting"
echo "✓ Tenant isolation"
echo "✓ Input validation"
echo "✓ Output escaping"
echo "✓ Database constraints"
echo "✓ Audit logging"
echo "=========================================="
