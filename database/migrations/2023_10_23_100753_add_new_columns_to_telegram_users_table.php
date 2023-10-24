<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewColumnsToTelegramUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->string('mode')->nullable()->after('telegram_id');
            $table->integer('curr_question_index')->nullable()->after('mode');
            $table->text('answer_1')->nullable()->after('curr_question_index');
            $table->text('answer_2')->nullable()->after('answer_1');
            $table->text('answer_3')->nullable()->after('answer_2');
            $table->text('answer_4')->nullable()->after('answer_3');
            $table->text('answer_5')->nullable()->after('answer_4');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn('mode');
            $table->dropColumn('curr_question_index');
            $table->dropColumn('answer_1');
            $table->dropColumn('answer_2');
            $table->dropColumn('answer_3');
            $table->dropColumn('answer_4');
            $table->dropColumn('answer_5');
        });
    }
}
