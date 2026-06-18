<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgramExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exercise_name' => ['required', 'string', 'max:' . config('workout.exercise_name_max')],
            'sets'          => ['required', 'integer', 'min:1', 'max:' . config('workout.sets_max')],
            'reps'          => ['required', 'string', 'max:' . config('workout.reps_max')],
            'notes'         => ['nullable', 'string'],
        ];
    }
}
