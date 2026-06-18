<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramDayExercise extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'exercise_name',
        'sets',
        'reps',
        'notes',
        'position',
    ];

    public function day(): BelongsTo
    {
        return $this->belongsTo(ProgramDay::class, 'program_day_id');
    }
}
