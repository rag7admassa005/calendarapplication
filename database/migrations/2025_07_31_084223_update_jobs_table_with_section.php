<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('jobs', function (Blueprint $table) {
              $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->dropForeign(['manager_id']); // إذا فيه FK
            $table->dropColumn('manager_id');
        });
    }

    public function down(): void {
        Schema::table('jobs', function (Blueprint $table) {
             $table->dropForeign(['section_id']);
            $table->dropColumn('section_id');
            $table->foreignId('manager_id')->constrained('managers')->onDelete('cascade');
        });
    }
};
