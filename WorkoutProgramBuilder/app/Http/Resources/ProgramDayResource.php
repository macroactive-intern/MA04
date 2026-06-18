<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'program_id' => $this->program_id,
            'position'   => $this->position,
            'label'      => $this->label,
            'exercises'  => ProgramDayExerciseResource::collection($this->whenLoaded('exercises')),
        ];
    }
}
