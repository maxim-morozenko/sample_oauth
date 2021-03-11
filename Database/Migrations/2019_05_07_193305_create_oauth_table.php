<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOauthTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('oauth_global', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->integer('user_id')->nullable();
            $table->string('user_internal', 1024)->unique();

            $table->string('email', 1024)->nullable();
            $table->text('profile')->nullable()->nullable();

            $table->string('screen_name', 1024);
            $table->string('avatar', 1024)->nullable();
            $table->integer('expires_stamp')->nullable();
            $table->dateTime('expires_date')->nullable();
            $table->boolean('active')->dafault(true);
            $table->text('dev_answer')->nullable();


            $table->string('hash')->unique();
            $table->string('type', 20);

            $table->string('token', 2048);
            $table->string('token_secret', 2048);

            $table->timestamps();

            $table->index([
                'id', 'user_internal', 'email',
                'hash', 'active', 'user_id',
            ], 'i_full_search_oauth_global');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::dropIfExists('oauth_global');
    }
}
