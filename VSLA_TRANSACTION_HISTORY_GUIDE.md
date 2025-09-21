# VSLA Transaction History and Edit Functionality

## Overview
This implementation adds comprehensive transaction history viewing and editing capabilities for VSLA (Village Savings and Loan Association) meetings. Users can view all transactions for a specific meeting and make changes based on their role permissions.

## Features

### 1. Transaction History View
- **URL**: `/vsla/transactions/history?meeting_id={id}`
- **Access**: Treasurer, Super Admin, Admin, or VSLA User with appropriate permissions
- **Features**:
  - View all transactions for a specific meeting
  - See transaction details including member, type, amount, status
  - Real-time status updates
  - Auto-refresh every 30 seconds

### 2. Transaction Editing
- **URL**: `/vsla/transactions/{id}/edit`
- **Access**: Treasurer, Super Admin, Admin, or VSLA User with edit permissions
- **Features**:
  - Edit pending transactions
  - Edit approved transactions (with reversal warning)
  - Cannot edit rejected transactions
  - Full transaction reversal for approved transactions

### 3. Transaction Deletion
- **Access**: Treasurer, Super Admin, Admin
- **Limitations**: Only pending transactions can be deleted

## Access Control

### Who Can Edit Transactions:
1. **Super Admin** - Full access to all features
2. **Tenant Admin** - Full access to all features within their tenant
3. **VSLA User Role** - Access if they have `vsla.transactions.edit` permission
4. **Treasurer** - Members assigned as treasurer in VSLA role assignments

### Permission Levels:
- **View History**: Can view transaction history for meetings
- **Edit Transactions**: Can modify transaction details
- **Delete Transactions**: Can remove pending transactions
- **Approve/Reject**: Can approve or reject pending transactions

## Usage Instructions

### For Administrators:
1. Navigate to VSLA Meetings
2. Select a meeting
3. Click "View Transaction History" or use the URL with meeting_id parameter
4. From the history view, you can:
   - Edit any transaction (except rejected ones)
   - Delete pending transactions
   - Approve/reject pending transactions

### For Treasurers:
1. Must be assigned as treasurer in VSLA role assignments
2. Can access the same functionality as administrators
3. Can edit and manage transactions for their VSLA group

### For VSLA Users:
1. Must have VSLA User role with appropriate permissions
2. Can view and edit transactions based on assigned permissions

## Transaction Status Flow:
- **Pending**: Can be edited, deleted, approved, or rejected
- **Approved**: Can be edited (triggers reversal), cannot be deleted
- **Rejected**: Cannot be edited or deleted

## Technical Implementation

### New Routes Added:
```php
Route::get('transactions/history', [VslaTransactionsController::class, 'transactionHistory'])
    ->name('vsla.transactions.history');
Route::get('transactions/{id}/edit', [VslaTransactionsController::class, 'edit'])
    ->name('vsla.transactions.edit');
Route::put('transactions/{id}', [VslaTransactionsController::class, 'update'])
    ->name('vsla.transactions.update');
Route::delete('transactions/{id}', [VslaTransactionsController::class, 'destroy'])
    ->name('vsla.transactions.destroy');
```

### New Permissions Added:
- `vsla.transactions.history` - View transaction history
- `vsla.transactions.edit` - Edit transactions
- `vsla.transactions.update` - Update transactions
- `vsla.transactions.destroy` - Delete transactions

### Helper Functions:
- `is_vsla_treasurer()` - Check if current user is a treasurer
- `has_vsla_access()` - Check if user has VSLA module access

## Security Features:
- All routes protected by VSLA access middleware
- Permission-based access control
- Transaction reversal for approved transactions
- Audit trail maintained through updated_user_id
- Database transactions ensure data integrity

## Views Created:
1. `resources/views/backend/admin/vsla/transactions/history.blade.php`
2. `resources/views/backend/admin/vsla/transactions/edit.blade.php`

## Integration Points:
- Links added to bulk-create view for easy navigation
- Breadcrumb navigation for better UX
- Real-time status updates
- Responsive design for mobile access

## Testing:
To test the functionality:
1. Enable VSLA module for a tenant
2. Create a VSLA meeting
3. Add some transactions via bulk-create
4. Navigate to transaction history
5. Test editing, approving, and rejecting transactions
6. Verify access control for different user roles
