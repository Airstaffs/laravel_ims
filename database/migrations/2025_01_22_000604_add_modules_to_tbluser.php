<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddModulesToTbluser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tbluser', function (Blueprint $table) {
            // Add columns for each module and make them nullable
            $table->boolean('order')->nullable()->default(false);
            $table->boolean('unreceived')->nullable()->default(false);
            $table->boolean('receiving')->nullable()->default(false);
            $table->boolean('labeling')->nullable()->default(false);
            $table->boolean('testing')->nullable()->default(false);
            $table->boolean('cleaning')->nullable()->default(false);
            $table->boolean('packing')->nullable()->default(false);
            $table->boolean('stockroom')->nullable()->default(false);
            
            // Add the main_module column as a varchar
            $table->string('main_module')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tbluser', function (Blueprint $table) {
            // Drop the columns if the migration is rolled back
            $table->dropColumn('order');
            $table->dropColumn('unreceived');
            $table->dropColumn('receiving');
            $table->dropColumn('labeling');
            $table->dropColumn('testing');
            $table->dropColumn('cleaning');
            $table->dropColumn('packing');
            $table->dropColumn('stockroom');
            
            // Drop the main_module column
            $table->dropColumn('main_module');
        });
    }
}
