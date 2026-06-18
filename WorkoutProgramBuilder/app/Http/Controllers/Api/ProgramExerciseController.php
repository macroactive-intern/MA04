<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderProgramExerciseRequest;
use App\Http\Requests\StoreProgramExerciseRequest;
use App\Models\Program;
use App\Models\ProgramDay;
use App\Models\ProgramDayExercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProgramExerciseController extends Controller
{
    public function store(StoreProgramExerciseRequest $request, Program $program, ProgramDay $day): JsonResponse
    {
        if ($program->coach_id !== auth()->id()) {
            abort(403);
        }

        if ($day->program_id !== $program->id) {
            abort(404);
        }

        $position = $day->exercises()->count() + 1;

        $day->exercises()->create(
            array_merge($request->validated(), ['position' => $position])
        );

        return response()->json($day->exercises()->get(), 201);
    }

    public function destroy(Program $program, ProgramDay $day, ProgramDayExercise $exercise): JsonResponse
    {
        if ($program->coach_id !== auth()->id()) {
            abort(403);
        }

        if ($day->program_id !== $program->id) {
            abort(404);
        }

        if ($exercise->program_day_id !== $day->id) {
            abort(404);
        }

        DB::transaction(function () use ($day, $exercise) {
            $deletedPosition = $exercise->position;

            $exercise->delete();

            ProgramDayExercise::where('program_day_id', $day->id)
                ->whereNull('deleted_at')
                ->where('position', '>', $deletedPosition)
                ->decrement('position');
        });

        return response()->json($day->exercises()->get());
    }

    public function reorder(ReorderProgramExerciseRequest $request, Program $program, ProgramDay $day, ProgramDayExercise $exercise): JsonResponse
    {
        if ($program->coach_id !== auth()->id()) {
            abort(403);
        }

        if ($day->program_id !== $program->id) {
            abort(404);
        }

        if ($exercise->program_day_id !== $day->id) {
            abort(404);
        }

        $newPosition = $request->integer('position');
        $oldPosition = $exercise->position;

        if ($newPosition !== $oldPosition) {
            DB::transaction(function () use ($day, $exercise, $oldPosition, $newPosition) {
                $exercise->update(['position' => 0]);

                if ($newPosition < $oldPosition) {
                    ProgramDayExercise::where('program_day_id', $day->id)
                        ->whereNull('deleted_at')
                        ->whereBetween('position', [$newPosition, $oldPosition - 1])
                        ->increment('position');
                } else {
                    ProgramDayExercise::where('program_day_id', $day->id)
                        ->whereNull('deleted_at')
                        ->whereBetween('position', [$oldPosition + 1, $newPosition])
                        ->decrement('position');
                }

                $exercise->update(['position' => $newPosition]);
            });
        }

        return response()->json($day->exercises()->get());
    }
}
