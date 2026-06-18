Step 1

    Project set up
                1. Start new Laravel project
                2. connect to Github repo
                                                                                                    10 mins

----------------------------------------------------------------------------------------------------------------

Step 2

    Documentation
                1. Write out the Understand.md
                2. Write out the Time Estimate.md
                3. Add the Ai Time estimate to the Estimate.md
                4. Write out the Aproach.md
                                                                                                        120 mins

----------------------------------------------------------------------------------------------------------------

Step 3

    Finish Project set up
                1. Install dependencies
                2. Install Sanctum
                3. Install Pest
                4. Confirm API/auth setup
                                                                                                    20 mins

----------------------------------------------------------------------------------------------------------------

Step 4

    Create migration for programs
        programs
            1. Add coach_id.
            2. Add name.
            3. Add description.
            4. Add timestamps.
            5. Add soft deletes.
            6. Add foreign key to users.
            7. Add scoped unique rule for coach/program name.

        program_days
            1. Add program_id.
            2. Add position.
            3. Add label.
            4. Add timestamps.
            5. add soft deletes to cascade soft-delete to days/exercises.
            6. Add foreign key to programs.
            7. Add unique constraint for program/day position.

        program_day_exercises
            1. Add program_day_id.
            2. Add exercise_name.
            3. Add sets.
            4. Add reps.
            5. Add notes.
            6. Add position.
            7. Add timestamps.
            8. Add soft deletes.
            9. Add foreign key to program_days.
            10. Add unique constraint for day/exercise position.
                                                                                                        30 mins

----------------------------------------------------------------------------------------------------------------

Step 5

    Create Models

            1. Create Program model
                - Add SoftDeletes.
                - Define fillable fields.
                - Define coach() relationship.
                - Define days() relationship.
                - Add ordered relationship if useful.
            
            2. Create ProgramDay model
                - Add SoftDeletes if using cascade soft delete.
                - Define fillable fields.
                - Define program() relationship.
                - Define exercises() relationship.
            
            3. Create ProgramDayExercise model
                - Add SoftDeletes if using cascade soft delete.
                - Define fillable fields.
                - Define programDay() relationship.
                                                                                                        30 mins

----------------------------------------------------------------------------------------------------------------

Step 6

    Validation

            1. Create StoreProgramRequest
                - Validate name.
                - Validate description.
                - Validate days.
                - Validate days.*.label.
                - Validate days.*.exercises.
                - Validate days.*.exercises.*.exercise_name.
                - Validate days.*.exercises.*.sets.
                - Validate days.*.exercises.*.reps.
                - Validate days.*.exercises.*.notes.

            2. Create update validation
                - Validate name.
                - Validate description.
                - Enforce scoped uniqueness by coach.
                - Ignore the current program when updating.
            
            3. Create add exercise validation
                - Validate exercise_name.
                - Validate sets.
                - Validate reps.
                - Validate notes.

            4. Create reorder validation
                - Validate position.
                - Ensure position is inside the current day’s exercise count.
                                                                                                        45 mins

----------------------------------------------------------------------------------------------------------------

Step 7

    Routes
        
            1. Add protected API route group
                Route::middleware('auth:sanctum')->group(function () {
                    //
                });

            2. Add program routes
                GET /api/programs
                POST /api/programs
                GET /api/programs/{id}
                PUT /api/programs/{id}
                DELETE /api/programs/{id}
            
            3. Add nested exercise routes
                POST /api/programs/{id}/days/{day}/exercises
                DELETE /api/programs/{id}/days/{day}/exercises/{exercise}
                PATCH /api/programs/{id}/days/{day}/exercises/{exercise}/reorder
                                                                                                        55 mins

----------------------------------------------------------------------------------------------------------------

Step 8

    Controller Logic

            1. Create ProgramController
                    - index
                    - store
                    - show
                    - update
                    - destroy
                                                                                                        30 mins

----------------------------------------------------------------------------------------------------------------

Step 9

    Add Exercise Logic

            1. Validate parent resources
                    - Find program.
                    - Check ownership.
                    - Find day.
                    - Confirm day belongs to program.
            2. Create exercise at end
                    - Count current exercises in the day.
                    - New position = count + 1.
                    - Create exercise row.
                    - Return updated day/exercise list.
                                                                                                        30 mins

----------------------------------------------------------------------------------------------------------------

Step 10

    Delete Exercise + Renumber

            1. Validate parent resources
                    - Find program.
                    - Check ownership.
                    - Find day.
                    - Confirm day belongs to program.
                    - Find exercise.
                    - Confirm exercise belongs to day.

            2. Delete and renumber inside transaction
                    - Start transaction.
                    - Store deleted exercise position.
                    - Delete exercise.
                    - Find remaining exercises with position greater than deleted position.
                    - Decrement their positions by 1.
                    - Commit transaction.
                                                                                                        40 mins

----------------------------------------------------------------------------------------------------------------

Step 11

    Reorder Exercise

            1. Validate parent resources
                    - Find program.
                    - Check ownership.
                    - Find day.
                    - Confirm day belongs to program.
                    - Find exercise.
                    - Confirm exercise belongs to day.
                    - Validate new position.

            2. Handle no-op reorder
            3. Move exercise upward
                    - Temporarily move selected exercise out of the way.
                    - Increment positions between new position and old position - 1.
                    - Set selected exercise to new position.

            4. Move exercise downward
                    - Temporarily move selected exercise out of the way.
                    - Decrement positions between old position + 1 and new position.
                    - Set selected exercise to new position.          
            
            5. Use transaction
                    - Wrap reorder in transaction.
                    - Avoid duplicate positions during update.
                    - Save final contiguous positions.
                                                                                                        40 mins

----------------------------------------------------------------------------------------------------------------

Step 12 

    Authorization

            1. Program ownership helper
            2. Make sure 403 happens instead of 404
            3. Nested ownership checks
                    - Check program ownership first.
                    - Then check day belongs to program.
                    - Then check exercise belongs to day.
                                                                                                        45 mins

----------------------------------------------------------------------------------------------------------------

Step 13

    Soft Delete Cascade

            1. Program soft delete
                    - Use SoftDeletes on Program.
                    - Confirm deleted program row remains in DB.
                    - Confirm deleted_at is set.
            
            2. Child cascade
                    - Add SoftDeletes to ProgramDay.
                    - Add SoftDeletes to ProgramDayExercise.
                    - On program delete, soft-delete days and exercises.

            3. Show endpoint behavior
                    - Show endpoint should not return deleted programs.
                    - Deleted days/exercises should not be returned.
                    - After deleting a program, the show endpoint should not return its days/exercises.
                                                                                                        30 mins

----------------------------------------------------------------------------------------------------------------

Step 14

    Tests

            1. Setup tests
                    - Use Pest or PHPUnit depending on project setup.
                    - Use RefreshDatabase.
                    - Create users/coaches.
                    - Authenticate with Sanctum.
            
            2. Test program creation
                    - Program is created.
                    - Days are created.
                    - Exercises are created.
                    - Day positions are [1, 2, ...].
                    - Exercise positions are [1, 2, ...].
            
            3. Test nested validation
                    - missing days.*.label type problems
                    - missing days.*.exercises.*.exercise_name
                    - invalid days.*.exercises.*.sets
                    - invalid days.*.exercises.*.reps
            
            4. Test scoped uniqueness
                    - Coach A creates "12-Week Strength Block".
                    - Coach B creates "12-Week Strength Block".
                    - Assert Coach B succeeds.
                    - Coach A tries to create another "12-Week Strength Block".
                    - Assert Coach A fails validation.

            5. Test ownership
                    - Coach A creates program.
                    - Coach B tries to read it.
                    - Assert 403.
                    - Coach B tries to update it.
                    - Assert 403.
                    - Coach B tries to delete it.
                    - Assert 403.
            
            6. Test delete exercise renumbering
            7. Test reorder upward
            8. Test reorder downward
            9. Test delete program soft deletes
                                                                                                        90 mins

----------------------------------------------------------------------------------------------------------------

Step 15
    
    Before/After Evidence
            1. Create BEFORE-AFTER.md
                                                                                                        20 mins

----------------------------------------------------------------------------------------------------------------

                                                                                                    10.5 hrs

---------------------------------------------------------------------------------------------------------------- 

AI estimate: 10.5–11.5 hrs total

Step	                    My estimate	    AI estimate	    Notes
1. Project setup	        10 min	        15 min	        Laravel install + GitHub can be quick, but allow small setup issues.
2. Documentation	        120 min	        100–130 min	    This is fair because the workflow requires careful UNDERSTANDING.md, ESTIMATE.md, and 
                                                            APPROACH.md.
3. Finish setup	            20 min	        25–35 min	    SQLite, Sanctum, API install, Pest setup, and auth check.
4. Migrations	            30 min	        40–50 min	    Three related tables, scoped unique indexes, soft deletes, and FK order need care.
5. Models	                30 min	        25–35 min	    Straightforward, but relationships and ordered relationships matter.
6. Validation	            45 min	        50–70 min	    Nested validation and scoped uniqueness can take longer.
7. Routes	                55 min	        15–25 min	    This one is overestimated. Routes are quick unless debugging route model binding.
8. Controller base logic	30 min	        60–80 min	    index, store, show, update, destroy with ownership checks will take more than 30.
9. Add exercise logic	    30 min	        30–45 min	    Fair estimate.
10. Delete + renumber	    40 min	        45–60 min	    Needs transaction and direct DB correctness.
11. Reorder exercise	    40 min	        60–90 min	    This is the hardest logic. Upward/downward movement plus unique position constraints 
                                                            can be fiddly.
12. Authorization	        45 min	        35–50 min	    Fair. The 403-not-404 rule needs careful query order.
13. Soft delete cascade	    30 min	        40–60 min	    Soft-deleting children cleanly can take more time.
14. Tests	                90 min	        150–210 min	    Your test estimate is low. The acceptance criteria require a lot of specific tests.
15. Before/After evidence	20 min	        25–40 min	    Need pasted terminal output, test output, and DB checks.