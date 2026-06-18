<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProgramController extends Controller
{
    public function index(): JsonResponse
    {
        $programs = Program::where('coach_id', auth()->id())->get();

        return response()->json(ProgramResource::collection($programs));
    }

    public function store(StoreProgramRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $program = DB::transaction(function () use ($validated) {
            $program = new Program($validated);
            $program->coach_id = auth()->id();
            $program->save();

            foreach ($validated['days'] as $dayIndex => $dayData) {
                $day = $program->days()->create([
                    'position' => $dayIndex + 1,
                    'label'    => $dayData['label'] ?? null,
                ]);

                foreach ($dayData['exercises'] as $exerciseIndex => $exerciseData) {
                    $day->exercises()->create([
                        'exercise_name' => $exerciseData['exercise_name'],
                        'sets'          => $exerciseData['sets'],
                        'reps'          => $exerciseData['reps'],
                        'notes'         => $exerciseData['notes'] ?? null,
                        'position'      => $exerciseIndex + 1,
                    ]);
                }
            }

            return $program;
        });

        $program->load('days.exercises');

        Log::info('program.created', ['program_id' => $program->id, 'coach_id' => $program->coach_id]);

        return response()->json(new ProgramResource($program), 201);
    }

    public function show(Program $program): JsonResponse
    {
        if ($program->coach_id !== auth()->id()) {
            abort(403);
        }

        $program->load('days.exercises');

        return response()->json(new ProgramResource($program));
    }

    public function update(UpdateProgramRequest $request, Program $program): JsonResponse
    {
        if ($program->coach_id !== auth()->id()) {
            abort(403);
        }

        $program->update($request->validated());

        Log::info('program.updated', ['program_id' => $program->id, 'coach_id' => auth()->id()]);

        return response()->json(new ProgramResource($program));
    }

    public function destroy(Program $program): Response
    {
        if ($program->coach_id !== auth()->id()) {
            abort(403);
        }

        DB::transaction(function () use ($program) {
            foreach ($program->days as $day) {
                $day->exercises()->delete();
            }

            $program->days()->delete();
            $program->delete();
        });

        Log::info('program.deleted', ['program_id' => $program->id, 'coach_id' => auth()->id()]);

        return response()->noContent();
    }
}
