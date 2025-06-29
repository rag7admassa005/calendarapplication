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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
               $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration'); // طول الموعد بالدقائق، يختار المستخدم 30 أو 60 حسب الجدول
            $table->enum('status', ['pending', 'approved', 'rejected', 'rescheduled', 'cancelled'])->default('pending');
            $table->foreignId('manager_id')->constrained('managers')->cascadeOnDelete();
            $table->foreignId('assistant_id')->nullable()->constrained('assistants')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
