<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateLpMineLogsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lp_mine_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->comment('用户ID');
            $table->decimal('total_lp',40, 20)->comment('总体力');
            $table->decimal('stake_lp',40, 20)->comment('装备体力');
            $table->decimal('share_lp',40, 20)->comment('分享体力');
            $table->decimal('global_lp', 40, 20)->comment('全网算力');
            $table->decimal('amount', 40, 20)->comment('产量');
            $table->decimal('rate', 40, 20)->comment('百分比');
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
