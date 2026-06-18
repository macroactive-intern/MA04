<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                              => [
                'required',
                'string',
                'max:' . config('workout.program_name_max'),
                Rule::unique('programs')
                    ->where('coach_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'description'                       => ['nullable', 'string'],

            'days'                              => ['required', 'array', 'min:1'],
            'days.*.label'                      => ['nullable', 'string', 'max:' . config('workout.day_label_max')],

            'days.*.exercises'                  => ['required', 'array', 'min:1'],
            'days.*.exercises.*.exercise_name'  => ['required', 'string', 'max:' . config('workout.exercise_name_max')],
            'days.*.exercises.*.sets'           => ['required', 'integer', 'min:1', 'max:' . config('workout.sets_max')],
            'days.*.exercises.*.reps'           => ['required', 'string', 'max:' . config('workout.reps_max')],
            'days.*.exercises.*.notes'          => ['nullable', 'string'],
        ];
    }
}
