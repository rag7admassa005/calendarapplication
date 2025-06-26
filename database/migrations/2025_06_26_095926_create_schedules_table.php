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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('day_of_week'); // مثلا 'Monday'
            $table->boolean('is_active')->default(true); // هل اليوم فيه أوقات متاحة؟
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('duration')->nullable();
            $table->integer('repeat_count')->default(1); // عدد الأسابيع التي يتكرر فيها الجدول
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
