<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tblstores', function (Blueprint $table) {
            $table->id('store_id'); // Primary key
            $table->unsignedBigInteger('owner_id'); // Owner ID (foreign key)
            $table->string('storename'); // Store name
            $table->string('ClientID'); // Client ID
            $table->string('clientsecret'); // Client secret
            $table->string('refreshtoken'); // Refresh token
            $table->string('MerchantID'); // Merchant ID
            $table->string('MarketplaceID'); // Merchant ID
    
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tblstores');
    }
};
