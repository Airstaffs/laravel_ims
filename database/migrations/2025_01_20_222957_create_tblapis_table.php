<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTblapisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tblapis', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->string('api_name');
            $table->string('client_id')->nullable();
            $table->string('client_secret')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('access_token')->nullable();
            $table->string('expires_in')->nullable();
            $table->integer('error_code')->nullable();
            $table->string('status')->nullable();
            $table->string('dev_id')->nullable();
            $table->timestamps(); // Adds created_at and updated_at columns
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblapis');
    }
}
