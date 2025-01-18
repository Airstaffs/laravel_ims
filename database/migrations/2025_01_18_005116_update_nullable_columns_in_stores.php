<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('tblstores', function (Blueprint $table) {
            $table->string('client_id')->nullable()->change(); 
            $table->string('client_secret')->nullable()->change(); 
            $table->string('refresh_token')->nullable()->change(); 
            $table->string('MerchantID')->nullable()->change(); // Make 'MerchantID' nullable
            $table->string('Marketplace')->nullable()->change(); // Make 'Marketplace' nullable
            $table->string('MarketplaceID')->nullable()->change(); // Make 'MarketplaceID' nullable
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
{
    Schema::table('tblstores', function (Blueprint $table) {
        $table->string('client_id')->nullable(false)->change();
        $table->string('client_secret')->nullable(false)->change();
        $table->string('refresh_token')->nullable(false)->change();
        $table->string('MerchantID')->nullable(false)->change(); // Make 'MerchantID' not nullable
        $table->string('Marketplace')->nullable(false)->change(); // Make 'Marketplace' not nullable
        $table->string('MarketplaceID')->nullable(false)->change(); // Make 'MarketplaceID' not nullable
    });
}
};
