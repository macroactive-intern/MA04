<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_day_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_day_id')->constrained('program_days');
            $table->string('exercise_name', 120);
            $table->unsignedTinyInteger('sets');
            $table->string('reps', 20);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->softDeletes();
        });

        // Partial unique index: program_day_id + position must be unique for active exercises only.
        DB::statement(
            'CREATE UNIQUE INDEX program_day_exercises_day_position_active_unique ON program_day_exercises (program_day_id, position) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('program_day_exercises');
    }
};
