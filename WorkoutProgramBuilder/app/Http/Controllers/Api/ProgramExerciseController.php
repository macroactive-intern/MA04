<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderProgramExerciseRequest;
use App\Http\Requests\StoreProgramExerciseRequest;
use App\Http\Resources\ProgramDayExerciseResource;
use App\Models\Program;
use App\Models\ProgramDay;
use App\Models\ProgramDayExercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $exercise = $day->exercises()->create(
            array_merge($request->validated(), ['position' => $position])
        );

        Log::info('exercise.created', ['exercise_id' => $exercise->id, 'program_id' => $program->id, 'coach_id' => auth()->id()]);

        return response()->json(ProgramDayExerciseResource::collection($day->exercises()->get()), 201);
    }

    public function destroy(Program $program, ProgramDay $day, ProgramDayExercise $exercise): Response
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

        Log::info('exercise.deleted', ['exercise_id' => $exercise->id, 'program_id' => $program->id, 'coach_id' => auth()->id()]);

        return response()->noContent();
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
                    // Moving upward — increment affected rows highest-first to avoid
                    // transient duplicate positions hitting the unique index.
                    ProgramDayExercise::where('program_day_id', $day->id)
                        ->whereNull('deleted_at')
                        ->whereBetween('position', [$newPosition, $oldPosition - 1])
                        ->orderBy('position', 'desc')
                        ->get()
                        ->each(fn ($e) => $e->update(['position' => $e->position + 1]));
                } else {
                    // Moving downward — decrement affected rows lowest-first.
                    ProgramDayExercise::where('program_day_id', $day->id)
                        ->whereNull('deleted_at')
                        ->whereBetween('position', [$oldPosition + 1, $newPosition])
                        ->orderBy('position', 'asc')
                        ->get()
                        ->each(fn ($e) => $e->update(['position' => $e->position - 1]));
                }

                $exercise->update(['position' => $newPosition]);
            });
        }

        Log::info('exercise.reordered', ['exercise_id' => $exercise->id, 'position' => $newPosition, 'coach_id' => auth()->id()]);

        return response()->json(ProgramDayExerciseResource::collection($day->exercises()->get()));
    }
}
