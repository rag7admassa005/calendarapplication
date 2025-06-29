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
              $table->foreignId('manager_id')->constrained('managers')->cascadeOnDelete();
            $table->enum('day_of_week', ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('repeat_for_weeks')->default(1);
            $table->unsignedInteger('meeting_duration_1')->default(30); // مدة أولى ثابتة (30 دقيقة)
            $table->unsignedInteger('meeting_duration_2')->default(60); // مدة ثانية ثابتة (60 دقيقة)
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
