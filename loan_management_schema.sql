-- Enhanced Loan Management Schema for IntelliCash
-- This extends the existing loan system to support comprehensive loan management

-- 1. Enhanced Loan Products Table
ALTER TABLE loan_products ADD COLUMN IF NOT EXISTS product_category ENUM('group_loan', 'business_loan') DEFAULT 'business_loan';
ALTER TABLE loan_products ADD COLUMN IF NOT EXISTS risk_level ENUM('low', 'medium', 'high') DEFAULT 'medium';
ALTER TABLE loan_products ADD COLUMN IF NOT EXISTS collateral_required BOOLEAN DEFAULT FALSE;
ALTER TABLE loan_products ADD COLUMN IF NOT EXISTS guarantor_required BOOLEAN DEFAULT TRUE;
ALTER TABLE loan_products ADD COLUMN IF NOT EXISTS max_guarantors INTEGER DEFAULT 2;
ALTER TABLE loan_products ADD COLUMN IF NOT EXISTS grace_period_days INTEGER DEFAULT 0;
ALTER TABLE loan_products ADD COLUMN IF NOT EXISTS auto_approval_limit DECIMAL(10,2) DEFAULT 0;
ALTER TABLE loan_products ADD COLUMN IF NOT EXISTS requires_business_plan BOOLEAN DEFAULT FALSE;
ALTER TABLE loan_products ADD COLUMN IF NOT EXISTS requires_financial_statements BOOLEAN DEFAULT FALSE;

-- 2. Loan Application Workflow Table
CREATE TABLE IF NOT EXISTS loan_applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    application_number VARCHAR(50) UNIQUE NOT NULL,
    loan_product_id BIGINT UNSIGNED NOT NULL,
    applicant_id BIGINT UNSIGNED NOT NULL,
    application_date DATE NOT NULL,
    loan_amount DECIMAL(10,2) NOT NULL,
    loan_purpose TEXT,
    business_type VARCHAR(100),
    business_plan_attachment VARCHAR(255),
    financial_statements_attachment VARCHAR(255),
    collateral_documents TEXT,
    guarantor_details TEXT,
    application_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'cancelled') DEFAULT 'draft',
    review_notes TEXT,
    approved_amount DECIMAL(10,2),
    approved_date DATE,
    approved_by BIGINT UNSIGNED,
    rejected_reason TEXT,
    created_user_id BIGINT UNSIGNED,
    updated_user_id BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (loan_product_id) REFERENCES loan_products(id) ON DELETE CASCADE,
    FOREIGN KEY (applicant_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 3. Enhanced Loans Table (extend existing)
ALTER TABLE loans ADD COLUMN IF NOT EXISTS loan_application_id BIGINT UNSIGNED;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS loan_category ENUM('group_loan', 'business_loan') DEFAULT 'business_loan';
ALTER TABLE loans ADD COLUMN IF NOT EXISTS disbursement_account_id BIGINT UNSIGNED;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS repayment_frequency ENUM('weekly', 'monthly', 'quarterly', 'annually') DEFAULT 'monthly';
ALTER TABLE loans ADD COLUMN IF NOT EXISTS grace_period_end_date DATE;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS risk_rating ENUM('low', 'medium', 'high') DEFAULT 'medium';
ALTER TABLE loans ADD COLUMN IF NOT EXISTS loan_officer_id BIGINT UNSIGNED;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS portfolio_id BIGINT UNSIGNED;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS expected_repayment_amount DECIMAL(10,2);
ALTER TABLE loans ADD COLUMN IF NOT EXISTS last_payment_date DATE;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS next_payment_date DATE;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS days_past_due INTEGER DEFAULT 0;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS loan_score DECIMAL(5,2) DEFAULT 0;

-- 4. Loan Portfolio Management
CREATE TABLE IF NOT EXISTS loan_portfolios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    portfolio_manager_id BIGINT UNSIGNED,
    risk_tolerance ENUM('conservative', 'moderate', 'aggressive') DEFAULT 'moderate',
    max_exposure_per_borrower DECIMAL(10,2),
    max_exposure_percent DECIMAL(5,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_user_id BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (portfolio_manager_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 5. Loan Risk Assessment
CREATE TABLE IF NOT EXISTS loan_risk_assessments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    loan_id BIGINT UNSIGNED NOT NULL,
    assessment_date DATE NOT NULL,
    credit_score INTEGER,
    income_stability_score INTEGER,
    business_viability_score INTEGER,
    collateral_adequacy_score INTEGER,
    guarantor_strength_score INTEGER,
    overall_risk_score INTEGER,
    risk_level ENUM('low', 'medium', 'high') NOT NULL,
    risk_factors TEXT,
    mitigation_measures TEXT,
    assessed_by BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    FOREIGN KEY (assessed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 6. Loan Monitoring and Alerts
CREATE TABLE IF NOT EXISTS loan_monitoring_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    loan_id BIGINT UNSIGNED NOT NULL,
    alert_type ENUM('payment_overdue', 'risk_deterioration', 'collateral_value_change', 'guarantor_issue', 'business_closure') NOT NULL,
    alert_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    alert_message TEXT NOT NULL,
    alert_date DATE NOT NULL,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolution_notes TEXT,
    resolved_by BIGINT UNSIGNED,
    resolved_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 7. Loan Automation Rules
CREATE TABLE IF NOT EXISTS loan_automation_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    rule_name VARCHAR(100) NOT NULL,
    rule_description TEXT,
    trigger_event ENUM('application_submitted', 'payment_overdue', 'risk_assessment', 'collateral_review') NOT NULL,
    conditions JSON,
    actions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    priority INTEGER DEFAULT 0,
    created_user_id BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 8. Loan Performance Analytics
CREATE TABLE IF NOT EXISTS loan_performance_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    loan_id BIGINT UNSIGNED NOT NULL,
    metric_date DATE NOT NULL,
    portfolio_at_risk_percent DECIMAL(5,2),
    portfolio_loss_percent DECIMAL(5,2),
    average_days_past_due DECIMAL(8,2),
    repayment_rate_percent DECIMAL(5,2),
    default_rate_percent DECIMAL(5,2),
    profitability_ratio DECIMAL(8,4),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);

-- Add foreign key constraints for enhanced loans table
ALTER TABLE loans ADD CONSTRAINT fk_loans_application_id 
    FOREIGN KEY (loan_application_id) REFERENCES loan_applications(id) ON DELETE SET NULL;
ALTER TABLE loans ADD CONSTRAINT fk_loans_disbursement_account 
    FOREIGN KEY (disbursement_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL;
ALTER TABLE loans ADD CONSTRAINT fk_loans_loan_officer 
    FOREIGN KEY (loan_officer_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE loans ADD CONSTRAINT fk_loans_portfolio 
    FOREIGN KEY (portfolio_id) REFERENCES loan_portfolios(id) ON DELETE SET NULL;

-- Indexes for performance
CREATE INDEX idx_loan_applications_tenant_status ON loan_applications(tenant_id, application_status);
CREATE INDEX idx_loan_applications_applicant ON loan_applications(applicant_id);
CREATE INDEX idx_loan_applications_product ON loan_applications(loan_product_id);
CREATE INDEX idx_loans_category ON loans(loan_category);
CREATE INDEX idx_loans_portfolio ON loans(portfolio_id);
CREATE INDEX idx_loans_risk_rating ON loans(risk_rating);
CREATE INDEX idx_loan_monitoring_alerts_loan_type ON loan_monitoring_alerts(loan_id, alert_type);
CREATE INDEX idx_loan_monitoring_alerts_resolved ON loan_monitoring_alerts(is_resolved, alert_level);
