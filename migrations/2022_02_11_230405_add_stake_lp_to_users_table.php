<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class AddStakeLpToUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('stake_lp', 40, 20, true)->default(0)->comment('质押LP');
            $table->decimal('share_lp', 40, 20, true)->default(0)->comment('分享LP');
            $table->decimal('lp_balance', 40, 20, true)->default(0)->comment('LP余额');
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
