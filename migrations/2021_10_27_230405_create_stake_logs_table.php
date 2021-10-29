<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateStakeLogsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stake_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('address')->comment('钱包地址');
            $table->string('pid')->comment('周期ID');
            $table->string('amount')->comment('数量');
            $table->string('tx_id')->comment('交易ID')->unique();
            $table->unsignedInteger('block_number')->comment('区块高度');
            $table->decimal('user_amount', 40, 20)->comment('数量');
            $table->decimal('user_balance', 40, 20)->comment('数量');
            $table->decimal('user_reward', 40, 20)->comment('数量');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_logs');
    }
}
