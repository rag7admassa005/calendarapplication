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
        Schema::table('assistant_activity', function (Blueprint $table) {
            $table->dropForeign(['appointment_request_id']);
                $table->dropColumn('appointment_request_id');
                 $table->nullableMorphs('related_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistant_activity', function (Blueprint $table) {
            // حذف أعمدة المورف
            $table->dropMorphs('related_to');

            // إعادة إنشاء الأعمدة القديمة
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('appointment_request_id')->nullable()->constrained('appointment_requests')->cascadeOnDelete();
        });
    }
};
