<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateBindLogsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bind_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('user')->comment('用户地址');
            $table->string('referrer')->comment('推荐人地址');
            $table->string('tx_id')->comment('总体力')->unique();
            $table->unsignedInteger('block_number')->comment('区块高度');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mine_logs');
    }
}
