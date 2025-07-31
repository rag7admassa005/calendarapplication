<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameAppointmentIdColumnInAssistantActivityTable extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_activity', function (Blueprint $table) {
            // نحذف العلاقة أولاً
            $table->dropForeign(['appointment_id']);
            // نعيد تسمية العمود
            $table->renameColumn('appointment_id', 'appointment_request_id');
        });

        // ثم نعيد إضافة العلاقة مع الجدول الصحيح
        Schema::table('assistant_activity', function (Blueprint $table) {
            $table->foreign('appointment_request_id')
                  ->references('id')->on('appointment_requests')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // في حالة rollback
        Schema::table('assistant_activity', function (Blueprint $table) {
            $table->dropForeign(['appointment_request_id']);
            $table->renameColumn('appointment_request_id', 'appointment_id');
        });

        Schema::table('assistant_activity', function (Blueprint $table) {
            $table->foreign('appointment_id')
                  ->references('id')->on('appointments')
                  ->cascadeOnDelete();
        });
    }
}
