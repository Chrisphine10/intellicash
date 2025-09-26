<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create trigger to update bank account balance on transaction insert
        try {
            DB::unprepared('
                CREATE TRIGGER update_bank_balance_insert
                AFTER INSERT ON bank_transactions
                FOR EACH ROW
                BEGIN
                    DECLARE balance_change DECIMAL(15,2);
                    
                    IF NEW.status = 1 THEN
                        IF NEW.dr_cr = "cr" THEN
                            SET balance_change = NEW.amount;
                        ELSE
                            SET balance_change = -NEW.amount;
                        END IF;
                        
                        UPDATE bank_accounts 
                        SET current_balance = current_balance + balance_change,
                            last_balance_update = NOW()
                        WHERE id = NEW.bank_account_id;
                    END IF;
                END
            ');
        } catch (\Exception $e) {
            // Trigger creation failed, continue without failing migration
            // This is common in shared hosting environments without SUPER privileges
        }

        // Create trigger to update bank account balance on transaction update
        try {
            DB::unprepared('
                CREATE TRIGGER update_bank_balance_update
                AFTER UPDATE ON bank_transactions
                FOR EACH ROW
                BEGIN
                    DECLARE old_balance_change DECIMAL(15,2);
                    DECLARE new_balance_change DECIMAL(15,2);
                    
                    -- Calculate old balance change
                    IF OLD.status = 1 THEN
                        IF OLD.dr_cr = "cr" THEN
                            SET old_balance_change = OLD.amount;
                        ELSE
                            SET old_balance_change = -OLD.amount;
                        END IF;
                    ELSE
                        SET old_balance_change = 0;
                    END IF;
                    
                    -- Calculate new balance change
                    IF NEW.status = 1 THEN
                        IF NEW.dr_cr = "cr" THEN
                            SET new_balance_change = NEW.amount;
                        ELSE
                            SET new_balance_change = -NEW.amount;
                        END IF;
                    ELSE
                        SET new_balance_change = 0;
                    END IF;
                    
                    -- Update balance
                    UPDATE bank_accounts 
                    SET current_balance = current_balance - old_balance_change + new_balance_change,
                        last_balance_update = NOW()
                    WHERE id = NEW.bank_account_id;
                END
            ');
        } catch (\Exception $e) {
            // Trigger creation failed, continue without failing migration
        }

        // Create trigger to update bank account balance on transaction delete
        try {
            DB::unprepared('
                CREATE TRIGGER update_bank_balance_delete
                AFTER DELETE ON bank_transactions
                FOR EACH ROW
                BEGIN
                    DECLARE balance_change DECIMAL(15,2);
                    
                    IF OLD.status = 1 THEN
                        IF OLD.dr_cr = "cr" THEN
                            SET balance_change = -OLD.amount;
                        ELSE
                            SET balance_change = OLD.amount;
                        END IF;
                        
                        UPDATE bank_accounts 
                        SET current_balance = current_balance + balance_change,
                            last_balance_update = NOW()
                        WHERE id = OLD.bank_account_id;
                    END IF;
                END
            ');
        } catch (\Exception $e) {
            // Trigger creation failed, continue without failing migration
        }

        // Create trigger to prevent negative balances (if not allowed)
        try {
            DB::unprepared('
                CREATE TRIGGER prevent_negative_balance
                BEFORE UPDATE ON bank_accounts
                FOR EACH ROW
                BEGIN
                    IF NEW.allow_negative_balance = 0 AND NEW.current_balance < NEW.minimum_balance THEN
                        SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "Insufficient balance: Cannot go below minimum balance";
                    END IF;
                    
                    IF NEW.maximum_balance IS NOT NULL AND NEW.current_balance > NEW.maximum_balance THEN
                        SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "Balance limit exceeded: Cannot exceed maximum balance";
                    END IF;
                END
            ');
        } catch (\Exception $e) {
            // Trigger creation failed, continue without failing migration
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS update_bank_balance_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS update_bank_balance_update');
        DB::unprepared('DROP TRIGGER IF EXISTS update_bank_balance_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_negative_balance');
    }
};
