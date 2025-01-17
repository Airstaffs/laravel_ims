<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateTbldefinitionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tbldefinitions', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('category'); // Column for category
            $table->string('name'); // Column for name
            $table->text('value'); // Column for value
            $table->timestamps(); // Created_at and Updated_at
        });

        // Inject default data
        $marketplaces = [
            ['category' => 'Marketplace', 'name' => 'United States', 'value' => 'ATVPDKIKX0DER'],
            ['category' => 'Marketplace', 'name' => 'Canada', 'value' => 'A2EUQ1WTGCTBG2'],
            ['category' => 'Marketplace', 'name' => 'Mexico', 'value' => 'A1AM78C64UM0Y8'],
            ['category' => 'Marketplace', 'name' => 'United Kingdom', 'value' => 'A1F83G8C2ARO7P'],
            ['category' => 'Marketplace', 'name' => 'Germany', 'value' => 'A1PA6795UKMFR9'],
            ['category' => 'Marketplace', 'name' => 'France', 'value' => 'A13V1IB3VIYZZH'],
            ['category' => 'Marketplace', 'name' => 'Italy', 'value' => 'APJ6JRA9NG5V4'],
            ['category' => 'Marketplace', 'name' => 'Spain', 'value' => 'A1RKKUPIHCS9HS'],
            ['category' => 'Marketplace', 'name' => 'Japan', 'value' => 'A1VC38T7YXB528'],
            ['category' => 'Marketplace', 'name' => 'India', 'value' => 'A21TJRUUN4KGV'],
            ['category' => 'Marketplace', 'name' => 'Australia', 'value' => 'A39IBJ37TRP1C6'],
        ];

        DB::table('tbldefinitions')->insert($marketplaces);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tbldefinitions');
    }
}
