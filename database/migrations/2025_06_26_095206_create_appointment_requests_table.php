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
           $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->date('preferred_date');
            $table->time('preferred_start_time');
            $table->time('preferred_end_time');
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'rescheduled', 'cancelled'])->default('pending');
            $table->timestamp('requested_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
     });  }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_requests');
    }
};
