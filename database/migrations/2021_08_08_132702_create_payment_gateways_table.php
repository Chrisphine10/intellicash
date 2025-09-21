<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentGatewaysTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30);
            $table->string('slug', 30);
            $table->string('image')->nullable();
            $table->tinyInteger('type')->default(1)->comment('0 = Manual, 1 = Automatic');
            $table->tinyInteger('status')->default(0);
            $table->tinyInteger('is_crypto')->default(0);
            $table->text('parameters')->nullable();
            $table->text('instructions')->nullable();
            $table->text('extra')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('payment_gateways');
    }
}
