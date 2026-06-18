<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderProgramExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'position' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $day = $this->route('day');

            if (! $day) {
                return;
            }

            $max = $day->exercises()->count();
            $requested = $this->integer('position');

            if ($requested > $max) {
                $validator->errors()->add(
                    'position',
                    "Position must be between 1 and {$max}."
                );
            }
        });
    }
}
