<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'coach_id'    => $this->coach_id,
            'name'        => $this->name,
            'description' => $this->description,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'days'        => ProgramDayResource::collection($this->whenLoaded('days')),
        ];
    }
}
