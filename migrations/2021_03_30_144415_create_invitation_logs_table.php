<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateInvitationLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invitation_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->comment('用户ID');
            $table->unsignedInteger('child_id')->comment('用户ID');
            $table->unsignedInteger('parent_id')->nullable()->comment('父节点');
            $table->smallInteger('level')->comment('层级');
            $table->timestamps();

            $table->unique(['user_id', 'child_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invitation_logs');
    }
}
