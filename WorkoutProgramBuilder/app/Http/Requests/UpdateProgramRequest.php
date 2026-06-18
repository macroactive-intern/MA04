<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $program = $this->route('program');

        return [
            'name' => [
                'required',
                'string',
                'max:' . config('workout.program_name_max'),
                Rule::unique('programs')
                    ->where('coach_id', $this->user()->id)
                    ->whereNull('deleted_at')
                    ->ignore($program),
            ],
            'description' => ['nullable', 'string'],
        ];
    }
}
