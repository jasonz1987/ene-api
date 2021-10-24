<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateBurnLogsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('burn_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->comment('用户ID');
            $table->string('tx_id')->comment('交易ID');
            $table->unsignedInteger('block_number')->comment('区块高度');
            $table->decimal('power', 40, 20)->comment('算力');
            $table->decimal('burn_cpu', 40, 20)->comment('销毁CPU');
            $table->decimal('burn_wx', 40, 20)->comment('销毁WX');
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
