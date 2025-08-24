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
    Schema::table('invitations', function (Blueprint $table) {
        // حذف أول عامودين بشكل صريح
        $table->dropColumn(['related_to_type', 'related_to_id']);

        // إضافة الأعمدة الجديدة
        $table->string('title');
        $table->text('description')->nullable();
        $table->date('date')->nullable();
        $table->time('time')->nullable();
        $table->integer('duration')->nullable();
    });
}

public function down()
{
    Schema::table('invitations', function (Blueprint $table) {

        $table->string('related_to_type')->nullable();
        $table->unsignedBigInteger('related_to_id')->nullable();

        $table->dropColumn(['title', 'description', 'date', 'time', 'duration']);
    });
}


};