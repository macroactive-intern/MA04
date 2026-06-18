# APPROACH.md

## Project

MA04 — Workout Program Builder

## Purpose of this document

This document describes how I will build the Workout Program Builder API before writing code.

It covers:

- Data model, table columns, types, and constraints
- Endpoints and routes
- Libraries/packages I will use and why
- Edge cases and how I will handle them
- Decisions I am making where the brief is ambiguous

---

## What I am building

I am building a Laravel JSON API for authenticated coaches to create and manage workout programs.

A workout program belongs to one coach. A program contains multiple ordered days. Each day contains multiple ordered exercises. The order matters because the client is expected to follow the workout exactly as the coach wrote it.

The API must allow a coach to:

- list their own programs
- create a full program with days and exercises in one request
- view one program with its full nested day/exercise structure
- update program metadata
- soft-delete a program
- add an exercise to a day
- delete an exercise and renumber the remaining exercises
- reorder an exercise inside a day

All endpoints will require `auth:sanctum`.

---

## Packages and tools I will use

## Laravel

I will use Laravel because this task is specified as a Laravel API task.

Laravel will provide:

- routing
- controllers
- migrations
- Eloquent models
- FormRequests
- validation
- database transactions
- feature testing support

## Laravel Sanctum

I will use Sanctum because the brief requires all endpoints to use `auth:sanctum`.

Sanctum will be used to authenticate API requests in tests and routes.

## SQLite

I will use SQLite because the project setup specifically says to configure `.env` for SQLite.

SQLite will be used for local development and automated tests.

## Eloquent ORM

I will use Eloquent models for:

- `Program`
- `ProgramDay`
- `ProgramDayExercise`

Eloquent relationships will make it clear how programs, days, and exercises connect.

## FormRequest validation

I will use a FormRequest for the create endpoint because the acceptance criteria specifically require create validation to be handled by a FormRequest.

The main required FormRequest will be:

```text
StoreProgramRequest
```

I may also create separate request classes for update, add exercise, and reorder to keep validation clean.

## Pest or PHPUnit

The brief mentions Laravel and tests. If Pest is installed in the project, I will use Pest. If not, I will use Laravel's default PHPUnit feature tests.

The tests need to prove the acceptance criteria, especially:

- nested create
- scoped uniqueness
- 403 ownership behavior
- delete renumbering
- reorder shifting
- soft deletes

---

## Schema decision: JSON note vs relational tables

The product note says:

> "We originally discussed storing each day's exercises as a JSON column since order is just an array — might be worth doing that for simplicity."

I will follow this note at the API boundary, but not as the database source of truth.

That means:

- The API accepts days and exercises as JSON arrays.
- The API returns days and exercises as JSON arrays.
- The database stores the exercises as relational rows in `program_day_exercises`.

I am doing this because the brief explicitly says there are three tables:

- `programs`
- `program_days`
- `program_day_exercises`

The acceptance criteria also require direct DB checks for contiguous exercise positions after delete and reorder. That is clearer and safer with relational rows than a JSON column.

I will not store exercises in both a JSON column and relational rows as equal sources of truth, because those can go out of sync.

Final decision:

```text
JSON arrays are the request/response shape.
Relational rows are the database source of truth.
```

---

## Data model

## Table: `programs`

| Column | Type | Constraints / notes |
| --- | --- | --- |
| `id` | `bigIncrements` | Primary key |
| `coach_id` | `foreignId` | References `users.id`; required |
| `name` | `string(120)` | Required |
| `description` | `text` | Nullable |
| `created_at` | timestamp | Laravel timestamps |
| `updated_at` | timestamp | Laravel timestamps |
| `deleted_at` | timestamp | Soft delete |

### `programs` constraints

Program names are unique per coach, not globally unique.

The acceptance criteria say two coaches can both have a program named `"12-Week Strength Block"`, so the unique rule must be scoped to `coach_id`.

Planned uniqueness rule:

```text
coach_id + name must be unique for active programs
```

Because soft deletes are used, I will aim to allow a coach to reuse a program name after the old program is soft-deleted.

In SQLite, the strongest version is a partial unique index:

```sql
CREATE UNIQUE INDEX programs_coach_name_active_unique
ON programs (coach_id, name)
WHERE deleted_at IS NULL;
```

If I do not use a raw partial index, I will still enforce this in validation using a scoped uniqueness rule that ignores soft-deleted rows.

---

## Table: `program_days`

| Column | Type | Constraints / notes |
| --- | --- | --- |
| `id` | `bigIncrements` | Primary key |
| `program_id` | `foreignId` | References `programs.id`; required |
| `position` | `unsignedSmallInteger` | Required; 1-based; contiguous within a program |
| `label` | `string(80)` | Nullable |
| `created_at` | timestamp | Laravel timestamps |
| `updated_at` | timestamp | Laravel timestamps |
| `deleted_at` | timestamp | Added for soft-delete cascade |

### `program_days` constraints

Each active day position must be unique inside a program.

Planned constraint:

```text
program_id + position must be unique for active days
```

This prevents duplicate day positions.

Because the brief says deleting a program should cascade to days/exercises, I will add soft deletes to this table even though the data model table does not explicitly list `deleted_at`.

---

## Table: `program_day_exercises`

| Column | Type | Constraints / notes |
| --- | --- | --- |
| `id` | `bigIncrements` | Primary key |
| `program_day_id` | `foreignId` | References `program_days.id`; required |
| `exercise_name` | `string(120)` | Required |
| `sets` | `unsignedTinyInteger` | Required; validated 1–255 |
| `reps` | `string(20)` | Required |
| `notes` | `text` | Nullable |
| `position` | `unsignedSmallInteger` | Required; 1-based; contiguous within a day |
| `created_at` | timestamp | Laravel timestamps |
| `updated_at` | timestamp | Laravel timestamps |
| `deleted_at` | timestamp | Added for soft-delete cascade |

### `program_day_exercises` constraints

Each active exercise position must be unique inside a day.

Planned constraint:

```text
program_day_id + position must be unique for active exercises
```

This prevents duplicate exercise positions.

The code must also make sure there are no gaps after delete or reorder.

---

## Model structure

## `Program`

The `Program` model will:

- use `SoftDeletes`
- belong to a coach/user
- have many program days
- return days ordered by `position`

Relationships:

```php
Program belongsTo User through coach_id
Program hasMany ProgramDay
```

Mass assignment:

- `name` and `description` can be fillable
- `coach_id` should not come from request input; it should be set from `auth()->id()`

---

## `ProgramDay`

The `ProgramDay` model will:

- use `SoftDeletes`
- belong to a program
- have many exercises
- return exercises ordered by `position`

Relationships:

```php
ProgramDay belongsTo Program
ProgramDay hasMany ProgramDayExercise
```

---

## `ProgramDayExercise`

The `ProgramDayExercise` model will:

- use `SoftDeletes`
- belong to a program day
- store exercise order using `position`

Relationship:

```php
ProgramDayExercise belongsTo ProgramDay
```

---

## Endpoints / routes

All routes will be inside:

```php
Route::middleware('auth:sanctum')->group(function () {
    //
});
```

## Program routes

| Method | URI | Controller action | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/programs` | `ProgramController@index` | List authenticated coach's programs |
| `POST` | `/api/programs` | `ProgramController@store` | Create program with days and exercises |
| `GET` | `/api/programs/{program}` | `ProgramController@show` | Show full program structure |
| `PUT` | `/api/programs/{program}` | `ProgramController@update` | Update program metadata |
| `DELETE` | `/api/programs/{program}` | `ProgramController@destroy` | Soft-delete program and cascade |

## Exercise routes

| Method | URI | Controller action | Purpose |
| --- | --- | --- | --- |
| `POST` | `/api/programs/{program}/days/{day}/exercises` | `ProgramExerciseController@store` | Add exercise to a day |
| `DELETE` | `/api/programs/{program}/days/{day}/exercises/{exercise}` | `ProgramExerciseController@destroy` | Delete exercise and renumber |
| `PATCH` | `/api/programs/{program}/days/{day}/exercises/{exercise}/reorder` | `ProgramExerciseController@reorder` | Move exercise to new position |

I will probably split exercise actions into `ProgramExerciseController` so that the main `ProgramController` stays focused on program-level actions.

---

## Validation approach

## `StoreProgramRequest`

The create endpoint will use `StoreProgramRequest`.

Rules:

```php
[
    'name' => ['required', 'string', 'max:120'],
    'description' => ['nullable', 'string'],

    'days' => ['required', 'array', 'min:1'],
    'days.*.label' => ['nullable', 'string', 'max:80'],

    'days.*.exercises' => ['required', 'array', 'min:1'],
    'days.*.exercises.*.exercise_name' => ['required', 'string', 'max:120'],
    'days.*.exercises.*.sets' => ['required', 'integer', 'min:1', 'max:255'],
    'days.*.exercises.*.reps' => ['required', 'string', 'max:20'],
    'days.*.exercises.*.notes' => ['nullable', 'string'],
]
```

The `name` rule also needs scoped uniqueness for the authenticated coach.

---

## Update program validation

The update endpoint will validate:

```php
[
    'name' => ['required', 'string', 'max:120'],
    'description' => ['nullable', 'string'],
]
```

The update validation must:

- scope uniqueness to the authenticated coach
- ignore the program being updated
- ignore soft-deleted programs if name reuse is allowed

---

## Add exercise validation

The add exercise endpoint will validate:

```php
[
    'exercise_name' => ['required', 'string', 'max:120'],
    'sets' => ['required', 'integer', 'min:1', 'max:255'],
    'reps' => ['required', 'string', 'max:20'],
    'notes' => ['nullable', 'string'],
]
```

---

## Reorder validation

The reorder endpoint will validate:

```php
[
    'position' => ['required', 'integer', 'min:1'],
]
```

After basic validation, the controller will check that the requested position is less than or equal to the number of active exercises in that day.

Example:

If a day has four exercises, valid positions are:

```text
1, 2, 3, 4
```

A request for position `5` should fail validation.

---

## Controller approach

## `ProgramController@index`

Steps:

1. Get authenticated user.
2. Query programs where `coach_id = auth()->id()`.
3. Return only non-deleted programs.
4. Return JSON.

---

## `ProgramController@store`

Steps:

1. Validate with `StoreProgramRequest`.
2. Start a database transaction.
3. Create the program with `coach_id = auth()->id()`.
4. Loop through the `days` array.
5. Assign each day `position = index + 1`.
6. Create each `ProgramDay`.
7. Loop through each day's `exercises` array.
8. Assign each exercise `position = index + 1`.
9. Create each `ProgramDayExercise`.
10. Commit the transaction.
11. Return the created program with ordered days and exercises.

Reason for transaction:

If any nested insert fails, the API should not leave behind a half-created program.

---

## `ProgramController@show`

Steps:

1. Find the program by ID.
2. If it does not exist, return `404`.
3. If it exists but belongs to another coach, return `403`.
4. Load days ordered by `position`.
5. Load exercises ordered by `position`.
6. Return nested JSON.

Important:

I will not fetch programs like this:

```php
Program::where('coach_id', auth()->id())->findOrFail($id);
```

That would incorrectly return `404` for another coach's program.

Instead, I will fetch the program first, then authorize:

```php
$program = Program::findOrFail($id);

if ($program->coach_id !== auth()->id()) {
    abort(403);
}
```

---

## `ProgramController@update`

Steps:

1. Find the program by ID.
2. Check ownership.
3. Validate name and description.
4. Update only metadata fields.
5. Return updated program.

This endpoint will not update days or exercises.

---

## `ProgramController@destroy`

Steps:

1. Find the program by ID.
2. Check ownership.
3. Start a database transaction.
4. Soft-delete all exercises belonging to the program's days.
5. Soft-delete all days belonging to the program.
6. Soft-delete the program.
7. Commit transaction.
8. Return `204 No Content`.

After delete, a direct query such as:

```sql
SELECT * FROM programs WHERE id = 7;
```

should still show the program row, but `deleted_at` should now contain a timestamp.

---

## `ProgramExerciseController@store`

Steps:

1. Find program.
2. Check program ownership.
3. Find day.
4. Confirm day belongs to program.
5. Validate exercise input.
6. Count active exercises in the day.
7. Create new exercise with `position = count + 1`.
8. Return updated day/exercise data.

Example:

Before:

```text
A = 1
B = 2
C = 3
```

Add D.

After:

```text
A = 1
B = 2
C = 3
D = 4
```

---

## `ProgramExerciseController@destroy`

Steps:

1. Find program.
2. Check program ownership.
3. Find day.
4. Confirm day belongs to program.
5. Find exercise.
6. Confirm exercise belongs to day.
7. Start transaction.
8. Store deleted exercise's position.
9. Soft-delete the exercise.
10. Find active exercises in the same day where `position > deleted_position`.
11. Decrement each of those positions by `1`.
12. Commit transaction.
13. Return success response.

Example:

Before:

```text
A = 1
B = 2
C = 3
D = 4
```

Delete B.

After:

```text
A = 1
C = 2
D = 3
```

---

## `ProgramExerciseController@reorder`

Steps:

1. Find program.
2. Check program ownership.
3. Find day.
4. Confirm day belongs to program.
5. Find exercise.
6. Confirm exercise belongs to day.
7. Validate new position.
8. Run reorder inside a transaction.
9. Return updated exercise order.

### No-op reorder

If old position equals new position, no shift is needed.

Example:

```text
old position = 2
new position = 2
```

Return current order.

### Moving upward

Example:

Move C from position 3 to position 1.

Before:

```text
A = 1
B = 2
C = 3
D = 4
```

Algorithm:

1. Temporarily set C to a safe position outside the active range.
2. Increment positions between new position and old position minus 1:
   - A: `1 -> 2`
   - B: `2 -> 3`
3. Set C to position 1.

After:

```text
C = 1
A = 2
B = 3
D = 4
```

### Moving downward

Example:

Move A from position 1 to position 3.

Before:

```text
A = 1
B = 2
C = 3
D = 4
```

Algorithm:

1. Temporarily set A to a safe position outside the active range.
2. Decrement positions between old position plus 1 and new position:
   - B: `2 -> 1`
   - C: `3 -> 2`
3. Set A to position 3.

After:

```text
B = 1
C = 2
A = 3
D = 4
```

---

## Authorization approach

All endpoints require `auth:sanctum`.

Program ownership rule:

```text
program.coach_id must equal auth()->id()
```

For program routes:

- find the program first
- then check ownership
- return `403` if the program belongs to another coach

For nested routes:

1. check program ownership first
2. confirm the day belongs to that program
3. confirm the exercise belongs to that day

This gives the required behavior:

- another coach's program returns `403`
- invalid day under your own program returns `404`
- invalid exercise under your own day returns `404`

---

## Soft-delete cascade approach

The brief says deleting a program should soft-delete it and cascade to days/exercises.

The child table definitions do not list soft deletes, but I will add soft deletes to:

- `program_days`
- `program_day_exercises`

Reason:

If the program is soft-deleted but the child records are hard-deleted, the cascade is not really a soft-delete cascade.

I will handle cascade manually in the destroy action:

1. soft-delete exercises first
2. soft-delete days second
3. soft-delete program last

I will not use database `ON DELETE CASCADE` for the soft-delete behavior because database cascade would hard-delete rows.

---

## Edge cases and how I will handle them

## Another coach accesses a program they do not own

Example:

Coach B calls:

```text
GET /api/programs/42
```

where program 42 belongs to Coach A.

Expected result:

```text
403 Forbidden
```

Handling:

- find the program first
- compare `coach_id` with `auth()->id()`
- abort with 403 if they do not match

---

## Program does not exist

Expected result:

```text
404 Not Found
```

Handling:

- use `findOrFail`
- this is different from an existing program owned by another coach

---

## Day does not belong to program

Expected result:

```text
404 Not Found
```

Handling:

- after ownership passes, check `day.program_id === program.id`
- if false, return 404

---

## Exercise does not belong to day

Expected result:

```text
404 Not Found
```

Handling:

- check `exercise.program_day_id === day.id`
- if false, return 404

---

## Duplicate program name for same coach

Expected result:

```text
422 validation error
```

Handling:

- scoped uniqueness validation by `coach_id`
- DB unique index if possible

---

## Same program name for different coaches

Expected result:

```text
Allowed
```

Handling:

- uniqueness is scoped to `coach_id`, not global

---

## Creating a program with no days

Expected result:

```text
422 validation error
```

Handling:

```php
'days' => ['required', 'array', 'min:1']
```

---

## Creating a day with no exercises

Expected result:

```text
422 validation error
```

Handling:

```php
'days.*.exercises' => ['required', 'array', 'min:1']
```

---

## Exercise sets is 0

Expected result:

```text
422 validation error
```

Handling:

```php
'days.*.exercises.*.sets' => ['required', 'integer', 'min:1', 'max:255']
```

---

## Reorder position is too high

Example:

A day has 4 exercises and request asks for position 5.

Expected result:

```text
422 validation error
```

Handling:

- check active exercise count
- reject if requested position is greater than count

---

## Delete exercise at position 2 from four exercises

Before:

```text
A = 1
B = 2
C = 3
D = 4
```

Action:

Delete B.

After:

```text
A = 1
C = 2
D = 3
```

Handling:

- store deleted position
- soft-delete selected exercise
- decrement all active exercises after that position

---

## Reorder exercise from position 3 to position 1

Before:

```text
A = 1
B = 2
C = 3
D = 4
```

Action:

Move C to position 1.

After:

```text
C = 1
A = 2
B = 3
D = 4
```

Handling:

- temporarily move C out of range
- shift positions 1 and 2 down one slot
- set C to position 1

---

## Reorder to the same position

Expected result:

```text
No change
```

Handling:

- if old position equals new position, return current order without shifting anything

---

## Deleted program is shown after delete

Expected result:

```text
404 Not Found
```

Handling:

- normal Eloquent queries exclude soft-deleted programs
- show endpoint should not use `withTrashed`

---

## Days and exercises after program delete

Expected result:

```text
They are not returned by the show endpoint
```

Handling:

- soft-delete days and exercises during program delete
- normal relationships exclude soft-deleted rows

---

## Ambiguous decisions from the brief

## 1. JSON column or relational exercise table?

Decision:

Use JSON arrays for request/response, but relational rows for storage.

Reason:

The brief lists three tables and requires direct DB checks for positions.

---

## 2. Is program name uniqueness global or scoped?

Decision:

Scoped to coach.

Reason:

The acceptance criteria say two coaches can both have a program named `"12-Week Strength Block"`.

---

## 3. Can soft-deleted program names be reused?

Decision:

Yes, I will treat uniqueness as applying to active programs.

Reason:

Soft-deleted rows should not usually block a coach from recreating a program name.

---

## 4. Does `{day}` mean day ID or day position?

Decision:

`{day}` means `program_days.id`.

Reason:

That matches normal Laravel route model binding.

---

## 5. Does `{exercise}` mean exercise ID or exercise position?

Decision:

`{exercise}` means `program_day_exercises.id`.

Reason:

That matches normal Laravel route model binding and is more stable than using position.

---

## 6. Where does a newly added exercise go?

Decision:

Append it to the end of the day.

Reason:

The brief does not specify an insert position for the add endpoint.

---

## 7. Does update modify days/exercises?

Decision:

No.

Reason:

The brief says update program metadata, so I will only update `name` and `description`.

---

## 8. What status should delete return?

Decision:

`204 No Content`.

Reason:

This is a common response for successful delete endpoints.

---

## Test plan

I will write feature tests for the acceptance criteria.

## Test: create program with days and exercises

Assert:

- program is created
- days are created
- exercises are created
- day positions are `[1, 2, ...]`
- exercise positions are `[1, 2, ...]`

## Test: nested validation

Assert validation errors for:

- `days.*.label`
- `days.*.exercises.*.exercise_name`
- `days.*.exercises.*.sets`
- `days.*.exercises.*.reps`

## Test: scoped uniqueness

Assert:

- Coach A can create `"12-Week Strength Block"`
- Coach B can also create `"12-Week Strength Block"`
- Coach A cannot create another active `"12-Week Strength Block"`

## Test: ownership returns 403

Assert:

- Coach B cannot read Coach A's program
- Coach B cannot update Coach A's program
- Coach B cannot delete Coach A's program
- all return `403`, not `404`

## Test: delete exercise renumbers

Setup:

```text
A = 1
B = 2
C = 3
D = 4
```

Action:

Delete B.

Assert by direct DB check:

```text
A = 1
C = 2
D = 3
```

Also assert active positions are exactly:

```text
[1, 2, 3]
```

## Test: reorder upward

Setup:

```text
A = 1
B = 2
C = 3
D = 4
```

Action:

Move C to position 1.

Assert:

```text
C = 1
A = 2
B = 3
D = 4
```

## Test: reorder downward

Setup:

```text
A = 1
B = 2
C = 3
D = 4
```

Action:

Move A to position 3.

Assert:

```text
B = 1
C = 2
A = 3
D = 4
```

## Test: soft-delete program

Assert:

- program row still exists in DB
- `programs.deleted_at` is not null
- child days are soft-deleted
- child exercises are soft-deleted
- show endpoint does not return the deleted program

---

## Expected files

```text
app/Models/Program.php
app/Models/ProgramDay.php
app/Models/ProgramDayExercise.php

app/Http/Controllers/Api/ProgramController.php
app/Http/Controllers/Api/ProgramExerciseController.php

app/Http/Requests/StoreProgramRequest.php
app/Http/Requests/UpdateProgramRequest.php
app/Http/Requests/StoreProgramExerciseRequest.php
app/Http/Requests/ReorderProgramExerciseRequest.php

database/migrations/xxxx_xx_xx_create_programs_table.php
database/migrations/xxxx_xx_xx_create_program_days_table.php
database/migrations/xxxx_xx_xx_create_program_day_exercises_table.php

routes/api.php

tests/Feature/ProgramManagementTest.php

BEFORE-AFTER.md
```

---

## Final checklist before coding

Before writing implementation code, this approach should answer:

- what tables I will create
- what fields each table has
- what constraints matter
- what routes I will build
- what packages I will use and why
- how validation works
- how delete renumbering works
- how reorder shifting works
- how ownership returns 403 instead of 404
- how soft-delete cascade works
- what ambiguous brief decisions I made
- what edge cases I will test
