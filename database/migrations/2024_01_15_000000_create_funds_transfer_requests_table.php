<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFundsTransferRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('funds_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('debit_account_id');
            $table->enum('transfer_type', ['kcb_buni', 'paystack_mpesa']);
            $table->decimal('amount', 15, 2);
            $table->string('beneficiary_name');
            $table->string('beneficiary_account')->nullable();
            $table->string('beneficiary_mobile')->nullable();
            $table->string('beneficiary_bank_code')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('transaction_id');
            $table->tinyInteger('status')->default(0)->comment('0=Pending, 1=Processing, 2=Completed, 3=Failed');
            $table->json('api_response')->nullable();
            $table->timestamps();
        });

        // Add foreign key constraints only if referenced tables exist
        if (Schema::hasTable('members')) {
            Schema::table('funds_transfer_requests', function (Blueprint $table) {
                $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('savings_accounts')) {
            Schema::table('funds_transfer_requests', function (Blueprint $table) {
                $table->foreign('debit_account_id')->references('id')->on('savings_accounts')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('transactions')) {
            Schema::table('funds_transfer_requests', function (Blueprint $table) {
                $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('funds_transfer_requests');
    }
}
