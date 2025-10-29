<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tblkanbancomments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('taskId');
            $table->unsignedBigInteger('userId')->nullable(); // make nullable
            $table->text('content')->nullable();
            $table->json('medias')->nullable(); // array of image paths
            $table->json('files')->nullable();  // array of file paths
            $table->timestamps();

            // Foreign key references
            $table->foreign('taskId')
                ->references('id')->on('tblkanbantask')
                ->onDelete('cascade');

            $table->foreign('userId')
                ->references('id')->on('tbluser')
                ->onDelete('set null'); // keep comment, just remove user link
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tblkanbancomments');
    }
};
