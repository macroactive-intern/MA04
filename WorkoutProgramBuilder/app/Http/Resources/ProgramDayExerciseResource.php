<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramDayExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'program_day_id' => $this->program_day_id,
            'exercise_name'  => $this->exercise_name,
            'sets'           => $this->sets,
            'reps'           => $this->reps,
            'notes'          => $this->notes,
            'position'       => $this->position,
        ];
    }
}
