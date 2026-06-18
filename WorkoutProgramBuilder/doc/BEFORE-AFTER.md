   PASS  Tests\Unit\ExampleTest
  ✓ that true is true

   PASS  Tests\Feature\ExampleTest
  ✓ the application returns a successful response                                                                                                      0.14s  

   FAIL  Tests\Feature\ProgramManagementTest
  ✓ coach can create a program with days and exercises                                                                                                 0.13s  
  ✓ label must be a string                                                                                                                             0.02s  
  ✓ exercise_name is required                                                                                                                          0.01s  
  ✓ sets must be at least 1                                                                                                                            0.01s  
  ✓ reps is required                                                                                                                                   0.01s  
  ✓ two coaches can share the same program name                                                                                                        0.02s  
  ✓ one coach cannot create two active programs with the same name                                                                                     0.02s  
  ✓ another coach gets 403 on show, update, and delete                                                                                                 0.02s  
  ✓ deleting an exercise renumbers remaining exercises contiguously                                                                                    0.01s  
  ⨯ moving an exercise upward shifts others down                                                                                                       0.03s  
  ✓ moving an exercise downward shifts others up                                                                                                       0.01s  
  ⨯ deleting a program soft-deletes it and cascades to days and exercises                                                                              0.02s  
  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ProgramManagementTest > moving an exercise upward shifts others down                                                                 
  Expected response status code [200] but received 500.
Failed asserting that 500 is identical to 200.

SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: program_day_exercises.program_day_id, program_day_exercises.position (Connection: sqlite, Database: :memory:, SQL: update "program_day_exercises" set "position" = "position" + 1, "updated_at" = 2026-06-18 01:13:34 where "program_day_id" = 1 and "deleted_at" is null and "position" between 1 and 2 and "program_day_exercises"."deleted_at" is null)

  at tests\Feature\ProgramManagementTest.php:186
    182▕     [$day, $exercises] = makeDayWithExercises($program);
    183▕ 
    184▕     $this->actingAs($coach)
    185▕         ->patchJson("/api/programs/{$program->id}/days/{$day->id}/exercises/{$exercises['C']->id}/reorder", ['position' => 1])
  ➜ 186▕         ->assertStatus(200);
    187▕ 
    188▕     expect(ProgramDayExercise::find($exercises['C']->id)->position)->toBe(1);
    189▕     expect(ProgramDayExercise::find($exercises['A']->id)->position)->toBe(2);
    190▕     expect(ProgramDayExercise::find($exercises['B']->id)->position)->toBe(3);

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  
   FAILED  Tests\Feature\ProgramManagementTest > deleting a program soft-deletes it and cascades to days and exercises                                        
  Expected response status code [204] but received 500.
Failed asserting that 500 is identical to 204.

App\Http\Controllers\Api\ProgramController::destroy(): Return value must be of type Illuminate\Http\JsonResponse, Illuminate\Http\Response returned

  at tests\Feature\ProgramManagementTest.php:220
    216▕     $programId = $this->actingAs($coach)
    217▕         ->postJson('/api/programs', samplePayload())
    218▕         ->json('id');
    219▕ 
  ➜ 220▕     $this->actingAs($coach)->deleteJson("/api/programs/{$programId}")->assertStatus(204);
    221▕ 
    222▕     $this->assertDatabaseHas('programs', ['id' => $programId]);
    223▕     expect(Program::withTrashed()->find($programId)->deleted_at)->not->toBeNull();
    224▕


  Tests:    2 failed, 12 passed (38 assertions)
  Duration: 0.58s

  