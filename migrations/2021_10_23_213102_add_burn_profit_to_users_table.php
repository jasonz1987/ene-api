<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class AddBurnProfitToUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('burn_profit', 40, 20 ,true)->default(0)->comment('销毁收益');
            $table->decimal('share_profit', 40, 20 ,true)->default(0)->comment('动态收益');
            $table->decimal('top_profit', 40, 20 ,true)->default(0)->comment('排名收益');
            $table->decimal('new_share_power', 40, 20, true)->default(0)->comment('挖矿算力');
            $table->decimal('new_team_power', 40, 20, true)->default(0)->comment('挖矿算力');
            $table->decimal('burn_power', 40, 20, true)->default(0)->comment('挖矿算力');
            $table->tinyInteger('share_status')->default(0)->comment('分享状态是否激活');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
}
