<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('tblkanbanactivitylog', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('taskId');
            $table->unsignedBigInteger('userId');
            $table->text('description');
            $table->timestamps();


            $table->foreign('taskId')
                ->references('id')
                ->on('tblkanbantasks')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tblkanbanactivitylog');
    }
};
