# 🚀 IntelliCash Seeder Management - Deployment Guide

This guide explains how to deploy IntelliCash to a new server and use the Seed Manager interface to populate all necessary data.

## 📋 Prerequisites

Before deploying, ensure your server has:
- PHP 8.2+ with required extensions
- MySQL/MariaDB database
- Composer installed
- Laravel environment configured

## 🔧 Deployment Steps

### 1. Initial Setup
```bash
# Clone the repository
git clone <your-repo-url> intellicash
cd intellicash

# Install dependencies
composer install --no-dev --optimize-autoloader

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env file
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=intellicash
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 2. Run Migrations
```bash
# Create database tables
php artisan migrate --force
```

### 3. Access Seed Manager
Navigate to your application URL and access the Seed Manager:
- **URL**: `https://yourdomain.com/admin/seeder-management`
- **Login**: Use the super admin credentials (will be created by DemoSeeder)

## 🎯 Using the Seed Manager

### Available Seeders

The system includes the following seeders organized by category:

#### **Core System** (Priority 1-3)
1. **Demo Data** - Creates IntelliDemo tenant, admin user, and basic system data
2. **Subscription Packages** - 7 packages (1 lifetime + 3 yearly + 3 monthly)
3. **Core Utilities** - System utilities and configuration data

#### **Communication** (Priority 4)
4. **Email Templates** - Email templates for all system modules

#### **Content** (Priority 5)
5. **Landing Page Content** - Landing page content and settings

#### **Payment** (Priority 6)
6. **Payment Gateways** - Payment gateway configurations

#### **Loans** (Priority 7)
7. **Loan Permissions** - Loan system permissions and settings

#### **Modules** (Priority 8-9)
8. **Voting System** - Voting system configuration and sample data
9. **Asset Management** - Asset management categories and sample data

#### **Compliance** (Priority 10-13)
10. **Legal Templates** - Legal templates and compliance documents
11. **Kenyan Legal Compliance** - Kenyan legal compliance templates
12. **Loan Terms and Privacy** - Loan terms and privacy policy templates
13. **Multi-Country Legal Templates** - Legal templates for multiple countries

### Recommended Seeding Order

#### **Option 1: Run All Core Seeders (Recommended)**
Click the **"Run All Core Seeders"** button. This will automatically run:
- Demo Data
- Subscription Packages
- Core Utilities
- Email Templates
- Landing Page Content
- Payment Gateways
- Loan Permissions

#### **Option 2: Individual Seeding**
Run seeders individually in priority order:
1. Start with **Demo Data** (creates tenant and admin user)
2. Run **Subscription Packages** 
3. Run other seeders as needed

### After Seeding

#### **System Status Should Show:**
- **Tenants**: 1 (IntelliDemo)
- **Users**: 2 (Super Admin + IntelliDemo Admin)
- **Packages**: 7 (1 lifetime + 3 yearly + 3 monthly)
- **Currencies**: 4 (KES, USD, and others)
- **Roles**: 6 (Admin, Manager, Staff, Agent, Viewer, VSLA User)

#### **Login Credentials:**
- **Super Admin**: `admin@demo.com` / `123456`
- **IntelliDemo Admin**: `admin@intellidemo.com` / `123456`
- **IntelliDemo Login URL**: `https://yourdomain.com/intelli-demo/login`

## 🔒 Security Considerations

### Production Deployment
1. **Change Default Passwords**: Update all default passwords
2. **Update Email Addresses**: Change demo email addresses to your domain
3. **Environment Configuration**: Ensure production settings in `.env`
4. **SSL Certificate**: Install SSL certificate for HTTPS
5. **File Permissions**: Set proper file permissions (755 for directories, 644 for files)

### Recommended Security Updates
```bash
# After initial setup, update passwords
php artisan tinker
# Then run: Hash::make('your_secure_password')
```

## 🐛 Troubleshooting

### Common Issues

#### **Seeder Fails with STDIN Error**
- **Solution**: This has been fixed in the code. The seeders now work in both web and CLI contexts.

#### **"Table Missing" Status**
- **Solution**: Run migrations first: `php artisan migrate --force`

#### **Duplicate Entry Errors**
- **Solution**: Use "Clear Existing Data" option when re-running seeders

#### **Permission Denied**
- **Solution**: Ensure web server has write permissions to storage and bootstrap/cache directories

### Manual CLI Seeding (Alternative)
If the web interface fails, you can run seeders manually:

```bash
# Run individual seeders
php artisan db:seed --class=DemoSeeder --force
php artisan db:seed --class=SubscriptionPackagesSeeder --force
php artisan db:seed --class=UtilitySeeder --force

# Or run all seeders
php artisan db:seed --force
```

## 📊 Package Structure

The system includes 7 subscription packages:

### Monthly Packages (3)
- **Basic Monthly**: KES 1,999/month
- **Standard Monthly**: KES 4,999/month (Popular)
- **Professional Monthly**: KES 9,999/month

### Yearly Packages (3)
- **Basic Yearly**: KES 19,990/year (2 months free)
- **Standard Yearly**: KES 49,990/year (2 months free)
- **Professional Yearly**: KES 99,990/year (2 months free)

### Lifetime Package (1)
- **Lifetime Plan**: KES 99,999 (One-time payment)

## 🎉 Success Verification

After successful deployment and seeding:

1. ✅ **System Status**: All counters show expected values
2. ✅ **Login Access**: Can login to both super admin and tenant admin
3. ✅ **Package Selection**: All 7 packages are available
4. ✅ **Tenant Access**: IntelliDemo tenant is fully functional
5. ✅ **Role Management**: All 6 roles are created with proper permissions

## 📞 Support

If you encounter any issues during deployment:
1. Check the Laravel logs: `storage/logs/laravel.log`
2. Verify database connectivity
3. Ensure all required PHP extensions are installed
4. Check file permissions

---

**🎯 Ready to Go!** Your IntelliCash system is now ready for production use with the IntelliDemo tenant fully configured and all core data populated.
