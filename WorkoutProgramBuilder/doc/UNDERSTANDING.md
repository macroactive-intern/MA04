What is the task asking me to build?

I need to make a laravel JSON API to build workout programs. 

Coaches create and manage workout programs for their clients. A program can  only belong to one coach at a time. 

A program contants multiple days and each day multiple exercises.

The order of both days and exercises matters because the coach expects the client to follow the program exactly as written.

---------------------------------------------------------

API musts:

    - Listing the authenticated coach's programs.
    - Creating a full program in one request, including days and exercises.
    - Showing a program with its full nested day and exercise structure.
    - Updating program metadata.
    - Soft-deleting a program.
    - Adding an exercise to a specific day.
    - Removing an exercise from a specific day and renumbering the remaining exercises.
    - Reordering an exercise within a day while keeping positions contiguous.

All endpoints must require `auth:sanctum`. A coach can only manage their own programs.

----------------------------------------------------------------------------------------------------------------------------

Authentication input

    Every endpoint requires an authenticated Sanctum user

----------------------------------------------------------------------------------------------------------------------------

Program creation input

`POST /api/programs` accepts a JSON body like

```json
{
  "name": "12-Week Strength Block",
  "description": "...",
  "days": [
    {
      "label": "Day 1 — Upper Body",
      "exercises": [
        { "exercise_name": "Bench Press", "sets": 4, "reps": "6–8" },
        { "exercise_name": "Row", "sets": 3, "reps": "10–12" }
        { "exercise_name": "Overhead Press", "sets": 3, "reps": "8–10" },
        { "exercise_name": "Pull-up",     "sets": 3, "reps": "AMRAP" }
      ]
    }
  ]
}
```

------------------------------------

The api will assign the positions automatically from the order of the days array
same with exercises they will automaticlly assign to each day based on the exercise array

----------------------------------------------------------------------------------------------------------------------------

Program update input

`PUT /api/programs/{id}` updates only program metadata.

```json
{
  "name": "Updated Program Name",
  "description": "Updated description"
}
```

This endpoint will not update nested days or exercises, because as to the brief we only need to Update the program metadata.

----------------------------------------------------------------------------------------------------------------------------

Add exercise input

`POST /api/programs/{id}/days/{day}/exercises` adds one exercise to an existing day.

```json
{
  "exercise_name": "Incline Dumbbell Press",
  "sets": 3,
  "reps": "8–12",
  "notes": "Controlled tempo"
}
```

New exercises should be added at the end of the day by default. 

----------------------------------------------------------------------------------------------------------------------------

Reorder exercise input

`PATCH /api/programs/{id}/days/{day}/exercises/{exercise}/reorder` changes one exercise's position.

```json
{
  "position": 1
}
```

The position should be validated as a required integer

----------------------------------------------------------------------------------------------------------------------------

What it will return

This is a JSON API

    - `GET /api/programs` returns a list of the authenticated coach's non-deleted programs.
    - `POST /api/programs` returns the created program with days and exercises included.
    - `GET /api/programs/{id}` returns one program with all days and exercises ordered by position.
    - `PUT /api/programs/{id}` returns the updated program metadata.
    - `DELETE /api/programs/{id}` returns a success response, usually `204 No Content`.
    - `POST /api/programs/{id}/days/{day}/exercises` returns the newly added exercise, or the updated day structure.
    - `DELETE /api/programs/{id}/days/{day}/exercises/{exercise}` returns a success response, and the database should show the remaining exercises renumbered.
    - `PATCH /api/programs/{id}/days/{day}/exercises/{exercise}/reorder` returns the updated day exercise order, or the moved exercise.

For forbidden access, i will use 403, not 404, when a coach tries to access another coach's program.

----------------------------------------------------------------------------------------------------------------------------

JSON planning note and my schema decision

The note from the production team:

"We originally discussed storing each day's exercises as a JSON column since order is just an array — might be worth doing that for simplicity."

The create request sends each days exercises as an ordered Json array, the response should return them as an ordered JSON array. From the API user's point of view, exercises are represented as an array.

however i'm not going to store exercise only as a JSON column

I have decided to do both in a controlled way.

I will use JSON arrays for the API request and response shape

And i will use the relational `program_day_exercises` table as the database source of truth

meaning a coach can send exercises as a JSON array, but the application saves each exercise as its own row with a `position` colimn

I am choosing this approach because the brief mentions there being three tables for this task

- `programs`
- `program_days`
- `program_day_exercises`

We also need to require direct database checks for exercise positions after delete and reorder operations.

The JSON note affects how the API accepts and returns ordered exercises, but the database should still use the three-table relational design from the brief.

I will not store the same exercies in both a JSON column and relational rows as two equal sources of truth, because they could go out of sync

----------------------------------------------------------------------------------------------------------------------------

What unique means for the program names

in the  brief it says the program names must be unique. that can be taken 2 ways. it could mean globally so if one coach has a program named something, meaning another coach cant have a program named the same.

The brief does mention Two coaches should be able to have a program named the same, so meaning it shouldn't be global and should be based on the coach.

- Coach A can have "12-Week Strength Block"
- Coach B can also have "12-Week Strength Block"
- Coach A wont be able to make another program called "12-Week Strength Block"

Program uniqueness will only apply to active, non-deleted programs

----------------------------------------------------------------------------------------------------------------------------

Step-by-step trace: deleting exercise

Before deletion, the day's JSON exercise array contains four exercises:

| Exercise | Position |
|----------|----------|
| Exercise A | 1 |
| Exercise B | 2 |
| Exercise C | 3 |
| Exercise D | 4 |

Action:

Delete Exercise B, which is currently at position 2.

JSON-array delete and renumber rule:

1. Load the `program_days.exercises` JSON array.
2. Find the exercise matching the `{exercise}` route identifier.
3. Remove that exercise from the array.
4. Re-index the remaining array in its current order.
5. Rewrite every remaining exercise's `position` as `index + 1`.
6. Save the updated JSON array back to the `program_days.exercises` column.

Step by step:

1. Exercise B is removed from the JSON array.
2. The remaining array is now `[Exercise A, Exercise C, Exercise D]`.
3. Exercise A is assigned position 1.
4. Exercise C is assigned position 2.
5. Exercise D is assigned position 3.
6. The updated JSON array is saved.

After deletion:

| Exercise | Position |
|----------|----------|
| Exercise A | 1 |
| Exercise C | 2 |
| Exercise D | 3 |

----------------------------------------------------------------------------------------------------------------------------

Step-by-step trace: moving exercise

Before reorder, the day's JSON exercise array contains:

| Exercise | Position |
|----------|----------|
| Exercise A | 1 |
| Exercise B | 2 |
| Exercise C | 3 |
| Exercise D | 4 |

Action:

Move Exercise C from position 3 to position 1.

JSON-array reorder rule:

1. Load the `program_days.exercises` JSON array.
2. Find the exercise matching the `{exercise}` route identifier.
3. Remove that exercise from its current array index.
4. Insert it at the new requested index.
5. Re-index the full array.
6. Rewrite every exercise's `position` as `index + 1`.
7. Save the updated JSON array back to the `program_days.exercises` column.

Step by step:

1. Exercise C is selected as the moving exercise.
2. Exercise C is removed from index 2, which represented position 3.
3. The temporary array becomes `[Exercise A, Exercise B, Exercise D]`.
4. Exercise C is inserted at index 0, which represents position 1.
5. The new array order becomes `[Exercise C, Exercise A, Exercise B, Exercise D]`.
6. Exercise C is assigned position 1.
7. Exercise A is assigned position 2.
8. Exercise B is assigned position 3.
9. Exercise D is assigned position 4.
10. The updated JSON array is saved.

After reorder:

| Exercise | Position |
|----------|----------|
| Exercise C | 1 |
| Exercise A | 2 |
| Exercise B | 3 |
| Exercise D | 4 |

----------------------------------------------------------------------------------------------------------------------------

Soft delete cascade is not fully clear

The brief says deleting a program should soft-delete it and cascade to days/exercises.

so for this i will add soft deletes to days and exercises too

---------------------------------------------------------

Route parameter `{day}` is not fully defined

I will assume `{day}` means the `program_days.id`

---------------------------------------------------------

Route parameter `{exercise}` is not fully defined

I will assume `{exercise}` means the `program_day_exercises.id`

---------------------------------------------------------

Reorder request body is missing

Assume the request body uses:

```json
{
  "position": 1
}

---------------------------------------------------------

Add exercise position behavior is not directly stated

The brief says positions are assigned automatically during program creation.

but it does not say where a newly added exercise will go.

so i will go with having a newly added exercise is appended to the end of day

---------------------------------------------------------

Update endpoint scope is unclear

The update endpoint should update program metadata

Meaning only `name` and `description`, not nested days or exercises.

---------------------------------------------------------

Delete response code is not specified

it should use `204 No Content`

---------------------------------------------------------

Empty days are not clearly allowed or rejected

Each day being added into the program should have at least have one exercise. and when called to view they  should only show each day with exercises.

---------------------------------------------------------

Programs with zero days are not clearly allowed or rejected

`days` should be required and should contain at least one day.

---------------------------------------------------------

Sets validation needs a practical lower bound

The database column is `unsignedTinyInteger`, which technically allows 0 to 255.

A workout exercise with 0 sets does not make sense, so I assume validation should require `sets` to be between 1 and 255.

---------------------------------------------------------

Ownership behavior for nested resources must be consistent

- if the program exists but belongs to another coach, return `403`
- if the program belongs to the coach but the day does not belong to that program, return `404`
- if the day belongs to the program but the exercise does not belong to that day, return `404`

---------------------------------------------------------

Ordering must always be explicit

Because order is a core requirement, I should always use `orderBy('position')` when loading days and exercises.

---------------------------------------------------------

