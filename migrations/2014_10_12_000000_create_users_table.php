<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id')->primary();
            $table->string('address')->unique();
            $table->string('source_address')->nullable()->comment('来源地址');
            $table->string('password');
            $table->decimal('mine_power', 40, 20, true)->default(0)->comment('挖矿算力');
            $table->decimal('share_power', 40, 20, true)->default(0)->comment('挖矿算力');
            $table->decimal('team_power', 40, 20, true)->default(0)->comment('挖矿算力');
            $table->decimal('profit', 40, 20, true)->default(0)->comment('收益');
            $table->decimal('balance', 40, 20, true)->default(0)->comment('余额');
            $table->tinyInteger('vip_level')->default(0)->comment('VIP等级');
            $table->tinyInteger('is_valid')->default(0)->comment('是否有效');
            $table->unsignedInteger('team_valid_num')->default(0)->comment('有效账户数');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
