<?php

use App\Models\Program;
use App\Models\ProgramDay;
use App\Models\ProgramDayExercise;
use App\Models\User;

function samplePayload(): array
{
    return [
        'name'        => '12-Week Strength Block',
        'description' => 'Build strength',
        'days'        => [
            [
                'label'     => 'Day 1',
                'exercises' => [
                    ['exercise_name' => 'Squat',       'sets' => 3, 'reps' => '5'],
                    ['exercise_name' => 'Bench Press', 'sets' => 3, 'reps' => '8'],
                ],
            ],
            [
                'label'     => 'Day 2',
                'exercises' => [
                    ['exercise_name' => 'Deadlift', 'sets' => 1, 'reps' => '5'],
                ],
            ],
        ],
    ];
}

function makeCoach(): User
{
    return User::factory()->create();
}

function makeProgram(User $coach, string $name = 'Test Program'): Program
{
    $program = new Program(['name' => $name, 'description' => null]);
    $program->coach_id = $coach->id;
    $program->save();

    return $program;
}

function makeDayWithExercises(Program $program, array $names = ['A', 'B', 'C', 'D']): array
{
    $day = $program->days()->create(['label' => 'Day 1', 'position' => 1]);

    $exercises = [];
    foreach ($names as $i => $name) {
        $exercises[$name] = $day->exercises()->create([
            'exercise_name' => $name,
            'sets'          => 1,
            'reps'          => '5',
            'position'      => $i + 1,
        ]);
    }

    return [$day, $exercises];
}

// ─── program creation ─────────────────────────────────────────────────────────

test('coach can create a program with days and exercises', function () {
    $coach = makeCoach();

    $response = $this->actingAs($coach)->postJson('/api/programs', samplePayload());

    $response->assertStatus(201);

    $program = Program::where('coach_id', $coach->id)->first();
    expect($program)->not->toBeNull();

    $days = ProgramDay::where('program_id', $program->id)->orderBy('position')->get();
    expect($days->pluck('position')->all())->toBe([1, 2]);

    $day1Exercises = ProgramDayExercise::where('program_day_id', $days[0]->id)->orderBy('position')->get();
    expect($day1Exercises->pluck('position')->all())->toBe([1, 2]);

    $day2Exercises = ProgramDayExercise::where('program_day_id', $days[1]->id)->orderBy('position')->get();
    expect($day2Exercises->pluck('position')->all())->toBe([1]);
});

// ─── nested validation ────────────────────────────────────────────────────────

test('label must be a string', function () {
    $payload = samplePayload();
    $payload['days'][0]['label'] = ['not', 'a', 'string'];

    $this->actingAs(makeCoach())->postJson('/api/programs', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['days.0.label']);
});

test('exercise_name is required', function () {
    $payload = samplePayload();
    unset($payload['days'][0]['exercises'][0]['exercise_name']);

    $this->actingAs(makeCoach())->postJson('/api/programs', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['days.0.exercises.0.exercise_name']);
});

test('sets must be at least 1', function () {
    $payload = samplePayload();
    $payload['days'][0]['exercises'][0]['sets'] = 0;

    $this->actingAs(makeCoach())->postJson('/api/programs', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['days.0.exercises.0.sets']);
});

test('reps is required', function () {
    $payload = samplePayload();
    unset($payload['days'][0]['exercises'][0]['reps']);

    $this->actingAs(makeCoach())->postJson('/api/programs', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['days.0.exercises.0.reps']);
});

// ─── scoped uniqueness ────────────────────────────────────────────────────────

test('two coaches can share the same program name', function () {
    $coachA = makeCoach();
    $coachB = makeCoach();

    $this->actingAs($coachA)->postJson('/api/programs', samplePayload())->assertStatus(201);
    $this->actingAs($coachB)->postJson('/api/programs', samplePayload())->assertStatus(201);
});

test('one coach cannot create two active programs with the same name', function () {
    $coach = makeCoach();

    $this->actingAs($coach)->postJson('/api/programs', samplePayload())->assertStatus(201);
    $this->actingAs($coach)->postJson('/api/programs', samplePayload())->assertStatus(422);
});

// ─── ownership ────────────────────────────────────────────────────────────────

test('another coach gets 403 on show, update, and delete', function () {
    $coachA = makeCoach();
    $coachB = makeCoach();

    $programId = $this->actingAs($coachA)
        ->postJson('/api/programs', samplePayload())
        ->json('id');

    $this->actingAs($coachB)->getJson("/api/programs/{$programId}")->assertStatus(403);
    $this->actingAs($coachB)->putJson("/api/programs/{$programId}", ['name' => 'Hacked', 'description' => ''])->assertStatus(403);
    $this->actingAs($coachB)->deleteJson("/api/programs/{$programId}")->assertStatus(403);
});

// ─── delete exercise renumbering ──────────────────────────────────────────────

test('deleting an exercise renumbers remaining exercises contiguously', function () {
    $coach = makeCoach();
    $program = makeProgram($coach);
    [$day, $exercises] = makeDayWithExercises($program);

    $this->actingAs($coach)
        ->deleteJson("/api/programs/{$program->id}/days/{$day->id}/exercises/{$exercises['B']->id}")
        ->assertStatus(200);

    $positions = ProgramDayExercise::where('program_day_id', $day->id)
        ->whereNull('deleted_at')
        ->orderBy('position')
        ->pluck('position')
        ->all();

    expect($positions)->toBe([1, 2, 3]);
    expect(ProgramDayExercise::find($exercises['A']->id)->position)->toBe(1);
    expect(ProgramDayExercise::find($exercises['C']->id)->position)->toBe(2);
    expect(ProgramDayExercise::find($exercises['D']->id)->position)->toBe(3);
});

// ─── reorder upward ───────────────────────────────────────────────────────────

test('moving an exercise upward shifts others down', function () {
    $coach = makeCoach();
    $program = makeProgram($coach);
    [$day, $exercises] = makeDayWithExercises($program);

    $this->actingAs($coach)
        ->patchJson("/api/programs/{$program->id}/days/{$day->id}/exercises/{$exercises['C']->id}/reorder", ['position' => 1])
        ->assertStatus(200);

    expect(ProgramDayExercise::find($exercises['C']->id)->position)->toBe(1);
    expect(ProgramDayExercise::find($exercises['A']->id)->position)->toBe(2);
    expect(ProgramDayExercise::find($exercises['B']->id)->position)->toBe(3);
    expect(ProgramDayExercise::find($exercises['D']->id)->position)->toBe(4);
});

// ─── reorder downward ─────────────────────────────────────────────────────────

test('moving an exercise downward shifts others up', function () {
    $coach = makeCoach();
    $program = makeProgram($coach);
    [$day, $exercises] = makeDayWithExercises($program);

    $this->actingAs($coach)
        ->patchJson("/api/programs/{$program->id}/days/{$day->id}/exercises/{$exercises['A']->id}/reorder", ['position' => 3])
        ->assertStatus(200);

    expect(ProgramDayExercise::find($exercises['B']->id)->position)->toBe(1);
    expect(ProgramDayExercise::find($exercises['C']->id)->position)->toBe(2);
    expect(ProgramDayExercise::find($exercises['A']->id)->position)->toBe(3);
    expect(ProgramDayExercise::find($exercises['D']->id)->position)->toBe(4);
});

// ─── soft delete cascade ──────────────────────────────────────────────────────

test('deleting a program soft-deletes it and cascades to days and exercises', function () {
    $coach = makeCoach();

    $programId = $this->actingAs($coach)
        ->postJson('/api/programs', samplePayload())
        ->json('id');

    $this->actingAs($coach)->deleteJson("/api/programs/{$programId}")->assertStatus(204);

    $this->assertDatabaseHas('programs', ['id' => $programId]);
    expect(Program::withTrashed()->find($programId)->deleted_at)->not->toBeNull();

    $days = ProgramDay::withTrashed()->where('program_id', $programId)->get();
    expect($days->every(fn ($d) => $d->deleted_at !== null))->toBeTrue();

    foreach ($days as $day) {
        $exercises = ProgramDayExercise::withTrashed()->where('program_day_id', $day->id)->get();
        expect($exercises->every(fn ($e) => $e->deleted_at !== null))->toBeTrue();
    }

    $this->actingAs($coach)->getJson("/api/programs/{$programId}")->assertStatus(404);
});
