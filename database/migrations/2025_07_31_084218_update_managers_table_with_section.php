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
  Schema::table('managers', function (Blueprint $table) {
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
             $table->dropColumn('department');
        });
    }

    public function down(): void {
        Schema::table('managers', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropColumn('section_id');
              $table->string('department')->nullable();
        });
    }
};
