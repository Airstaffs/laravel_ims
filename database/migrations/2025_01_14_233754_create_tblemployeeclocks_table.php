<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tblemployeeclocks', function (Blueprint $table) {
            $table->id('ID');
            $table->integer('userid')->nullable();
            $table->string('Employee')->nullable();
            $table->dateTime('TimeIn')->nullable();
            $table->dateTime('TimeOut')->nullable();
            $table->string('Notes')->nullable();
            $table->date('DateToday')->nullable();
            $table->integer('Counter')->nullable();
            $table->string('CheckStatus')->nullable();
            $table->string('AdminNote')->nullable();
            $table->dateTime('shortbreak_start')->nullable();
            $table->dateTime('shortbreak_end')->nullable();
            $table->double('shortbreak_totaltime')->nullable();
            $table->string('shortbreak_status')->nullable();
            $table->dateTime('TimeInSet')->nullable();
            $table->string('removestatus')->nullable();
            $table->string('removeby')->nullable();
            $table->dateTime('removedate')->nullable();
            $table->integer('bonusminute')->default(0);
            $table->string('userPDF')->nullable();
            $table->string('adminPDF')->nullable();
            $table->string('systemNotes')->nullable();
            $table->string('excuse')->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('tblemployeeclocks');
    }
};
