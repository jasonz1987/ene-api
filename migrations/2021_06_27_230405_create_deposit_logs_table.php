<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateDepositLogsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deposit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->comment('用户ID');
            $table->unsignedInteger('token_id')->comment('TOKEN ID');
            $table->string('tx_id')->comment('交易ID')->unique();
            $table->unsignedInteger('block_number')->comment('区块高度');
            $table->unsignedInteger('power')->comment('体力');
            $table->tinyInteger('is_out')->default(0)->comment('是否出局');
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
