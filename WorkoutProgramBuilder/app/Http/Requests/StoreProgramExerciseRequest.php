<?php

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
            'exercise_name' => ['required', 'string', 'max:120'],
            'sets'          => ['required', 'integer', 'min:1', 'max:255'],
            'reps'          => ['required', 'string', 'max:20'],
            'notes'         => ['nullable', 'string'],
        ];
    }
}
