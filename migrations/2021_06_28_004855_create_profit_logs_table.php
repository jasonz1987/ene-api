<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateProfitLogsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->comment('用户ID');
            $table->string('tx_id')->nullable()->comment('交易ID');
            $table->string('fee_tx_id')->nullable()->comment('手续费交易ID');
            $table->decimal('amount', 40, 20, true)->comment('数量');
            $table->decimal('real_amount', 40, 20, true)->default(0)->comment('数量');
            $table->dateTime('confirmed_at')->nullable()->comment('确认时间');
            $table->tinyInteger('status')->default(0)->comment('状态');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profit_logs');
    }
}
