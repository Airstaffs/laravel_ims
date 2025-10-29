<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tblkanbanuserpermission', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('taskId');
            $table->unsignedBigInteger('userId');
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_comment')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();

            // Foreign key to tblkanbantasks table
            $table->foreign('taskId')
                ->references('id')
                ->on('tblkanbantasks')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tblkanbanuserpermission');
    }
};
