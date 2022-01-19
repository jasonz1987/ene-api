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
            $table->bigIncrements('id');
            $table->string('address')->unique();
            $table->string('source_address')->nullable()->comment('来源地址');
            $table->string('password');
            $table->decimal('equipment_power', 40, 20, true)->default(0)->comment('装备算力');
            $table->decimal('total_equipment_power', 40, 20, true)->default(0)->comment('累计装备算力');
            $table->decimal('share_power', 40, 20, true)->default(0)->comment('分享算力');
            $table->decimal('team_power', 40, 20, true)->default(0)->comment('团队算力');
            $table->decimal('bonus', 40, 20, true)->default(0)->comment('收益');
            $table->decimal('balance', 40, 20, true)->default(0)->comment('余额');
            $table->tinyInteger('team_level')->default(0)->comment('团队等级');
            $table->unsignedInteger('team_num')->default(0)->comment('团队人数');
            $table->unsignedInteger('team_performance')->default(0)->comment('团队业绩');
            $table->unsignedInteger('small_performance')->default(0)->comment('团队业绩');
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
