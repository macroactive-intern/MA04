<?php

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
                'max:120',
                Rule::unique('programs')
                    ->where('coach_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'description'                       => ['nullable', 'string'],

            'days'                              => ['required', 'array', 'min:1'],
            'days.*.label'                      => ['nullable', 'string', 'max:80'],

            'days.*.exercises'                  => ['required', 'array', 'min:1'],
            'days.*.exercises.*.exercise_name'  => ['required', 'string', 'max:120'],
            'days.*.exercises.*.sets'           => ['required', 'integer', 'min:1', 'max:255'],
            'days.*.exercises.*.reps'           => ['required', 'string', 'max:20'],
            'days.*.exercises.*.notes'          => ['nullable', 'string'],
        ];
    }
}
