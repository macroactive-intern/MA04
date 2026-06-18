<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('users');
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Partial unique index: coach_id + name must be unique for active programs only.
        // Allows a coach to reuse a name after the previous program is soft-deleted.
        DB::statement(
            'CREATE UNIQUE INDEX programs_coach_name_active_unique ON programs (coach_id, name) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
