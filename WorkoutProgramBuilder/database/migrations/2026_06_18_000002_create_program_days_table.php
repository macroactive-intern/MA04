<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs');
            $table->unsignedSmallInteger('position');
            $table->string('label', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Partial unique index: program_id + position must be unique for active days only.
        DB::statement(
            'CREATE UNIQUE INDEX program_days_program_position_active_unique ON program_days (program_id, position) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('program_days');
    }
};
