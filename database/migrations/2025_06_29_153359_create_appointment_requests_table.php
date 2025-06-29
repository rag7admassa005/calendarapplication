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
        Schema::create('appointment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('managers')->cascadeOnDelete();
            $table->date('preferred_date');
            $table->time('preferred_start_time');
            $table->time('preferred_end_time');
            $table->integer('preferred_duration'); // مدة الطلب (30 أو 60)
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'rescheduled', 'cancelled'])->default('pending');
            $table->timestamp('requested_at');
            $table->nullableMorphs('reviewed_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_requests');
    }
};
