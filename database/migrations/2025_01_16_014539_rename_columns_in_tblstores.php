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
        Schema::table('tblstores', function (Blueprint $table) {
            // Rename the columns
            $table->renameColumn('ClientID', 'client_id');
            $table->renameColumn('clientsecret', 'client_secret');
            $table->renameColumn('refreshtoken', 'refresh_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tblstores', function (Blueprint $table) {
            // Reverse the renaming
            $table->renameColumn('client_id', 'ClientID');
            $table->renameColumn('client_secret', 'clientsecret');
            $table->renameColumn('refresh_token', 'refreshtoken');
        });
    }
};
