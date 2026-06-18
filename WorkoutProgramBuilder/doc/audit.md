# Production-Ready Audit — Workout Program Builder

**Date:** 2026-06-18
**Rubric:** Production-Ready Rubric (rubric.md)
**Result:** 4 pass / 6 fail

> **Note:** The rubric was written for a Loot Drop Simulator. Domain-specific values (loot pity threshold, trade expiry, guild membership limits) do not exist in this project. Where a criterion references those concepts, the underlying principle (no magic numbers, log state changes) is applied to the equivalent operations in this codebase.

---

## Criterion 1 — Type Safety

**Result: FAIL**

`declare(strict_types=1)` is missing from every PHP file under `app/`. All 12 files are affected:

| File |
| --- |
| `app/Http/Controllers/Controller.php` |
| `app/Http/Controllers/Api/ProgramController.php` |
| `app/Http/Controllers/Api/ProgramExerciseController.php` |
| `app/Models/User.php` |
| `app/Models/Program.php` |
| `app/Models/ProgramDay.php` |
| `app/Models/ProgramDayExercise.php` |
| `app/Http/Requests/StoreProgramRequest.php` |
| `app/Http/Requests/UpdateProgramRequest.php` |
| `app/Http/Requests/StoreProgramExerciseRequest.php` |
| `app/Http/Requests/ReorderProgramExerciseRequest.php` |
| `app/Providers/AppServiceProvider.php` |

**What passes:** All public and protected methods declare typed parameters and return types. No untyped method signatures were found.

**Fix:** Add `declare(strict_types=1);` as the second line (after `<?php`) in every file listed above.

---

## Criterion 2 — Error Handling

**Result: PASS**

No raw `new \Exception(...)` is thrown for business-logic errors anywhere in `app/`. Authorization failures use `abort(403)` and missing resource relationships use `abort(404)`, which Laravel resolves to proper JSON error responses via the default exception handler.

There are no named exception classes in this codebase. This is acceptable here because the only failure modes are authorization and not-found errors, both of which map cleanly to HTTP abort helpers. If business logic grows to include states that require differentiated monitoring (e.g. a position conflict that should page on-call), named exceptions will be required at that point.

---

## Criterion 3 — Observability

**Result: FAIL**

No `Log::info()` or any other logging call exists in `app/`. The following state-changing operations emit no structured log entry:

| Action | File |
| --- | --- |
| `ProgramController::store` | `app/Http/Controllers/Api/ProgramController.php:22` |
| `ProgramController::update` | `app/Http/Controllers/Api/ProgramController.php:67` |
| `ProgramController::destroy` | `app/Http/Controllers/Api/ProgramController.php:78` |
| `ProgramExerciseController::store` | `app/Http/Controllers/Api/ProgramExerciseController.php:16` |
| `ProgramExerciseController::destroy` | `app/Http/Controllers/Api/ProgramExerciseController.php:35` |
| `ProgramExerciseController::reorder` | `app/Http/Controllers/Api/ProgramExerciseController.php:63` |

**Fix:** Add at minimum `Log::info()` with the entity ID and actor ID after each successful write. Example for `destroy`:

```php
Log::info('program.deleted', ['program_id' => $program->id, 'coach_id' => auth()->id()]);
```

---

## Criterion 4 — Configuration

**Result: FAIL**

Magic numbers are hardcoded in FormRequest validation rules. No custom `config/*.php` file exists for this project.

**Hardcoded values found:**

| Value | Location | Meaning |
| --- | --- | --- |
| `120` | `StoreProgramRequest:21`, `UpdateProgramRequest:23`, `StoreProgramExerciseRequest:17` | Max length for name/exercise_name |
| `80` | `StoreProgramRequest:29` | Max length for day label |
| `255` | `StoreProgramRequest:32`, `StoreProgramExerciseRequest:18` | Max sets |
| `20` | `StoreProgramRequest:33`, `StoreProgramExerciseRequest:19` | Max length for reps string |

**Fix:** Create `config/workout.php` and reference values via `config()`:

```php
// config/workout.php
return [
    'program_name_max'    => 120,
    'day_label_max'       => 80,
    'exercise_name_max'   => 120,
    'sets_max'            => 255,
    'reps_max'            => 20,
];
```

Then in requests: `'max:' . config('workout.program_name_max')`.

---

## Criterion 5 — Validation

**Result: PASS**

No validation callback issues repeated DB queries for the same data within a single request.

`StoreProgramRequest` uses a single `Rule::unique()->where()->whereNull()` which runs once per validation pass, not once per item in the `days` or `exercises` arrays. The unique rule is on `name` only, not on nested array items. No N+1 query pattern exists.

---

## Criterion 6 — Data Integrity

**Result: PASS**

Every operation that writes to more than one table is wrapped in `DB::transaction()`:

| Action | Tables written | Transaction |
| --- | --- | --- |
| `ProgramController::store` | programs, program_days, program_day_exercises | ✓ |
| `ProgramController::destroy` | program_day_exercises, program_days, programs | ✓ |
| `ProgramExerciseController::destroy` | program_day_exercises (delete + decrement) | ✓ |
| `ProgramExerciseController::reorder` | program_day_exercises (multiple position updates) | ✓ |

`ProgramExerciseController::store` writes to one table only (`program_day_exercises`) and does not require a transaction.

`lockForUpdate()` is not used. This project has no concurrent read-then-write patterns where two requests could race to claim the same resource (no inventory, escrow, or balance operations). The position assignment in `store` (`count + 1`) could theoretically collide under very high concurrency, but this is an acceptable risk for a coach-only write API.

---

## Criterion 7 — Security

**Result: PASS**

**Stack traces:** `.env` is gitignored and contains `APP_DEBUG=true` for local development only. The default Laravel exception handler suppresses stack traces in production when `APP_DEBUG=false`. No custom exception handler overrides this. As long as `APP_DEBUG=false` is set in production, no stack trace will reach the client.

**Auth middleware:** All eight API endpoints are inside a `Route::middleware('auth:sanctum')->group(...)` in `routes/api.php:12`. The standalone `/api/user` route at line 6 is also behind `auth:sanctum`.

**Admin middleware:** Not applicable — this API has no admin-only endpoints.

**Authorization:** Ownership is enforced in every controller action via direct `coach_id` comparison before any data is read or written.

---

## Criterion 8 — API Consistency

**Result: FAIL**

**Status codes** are mostly correct:

| Action | Code | Correct per rubric |
| --- | --- | --- |
| `index` | 200 | ✓ |
| `store` (program) | 201 | ✓ |
| `show` | 200 | ✓ |
| `update` | 200 | ✓ |
| `destroy` (program) | 204 | ✓ |
| `store` (exercise) | 201 | ✓ |
| `destroy` (exercise) | 200 | ✗ — returns body; rubric requires 204 for deletes |
| `reorder` | 200 | ✓ |

**API Resources:** Not used. All controllers return raw Eloquent models via `response()->json($model)`. The rubric requires consistent use of API resource objects. Raw model serialisation exposes all model attributes including timestamps and pivot data by default, and gives no single place to control the response shape.

**Fix:** Create `ProgramResource`, `ProgramDayResource`, and `ProgramDayExerciseResource` extending `JsonResource` and use them in all controller responses.

---

## Criterion 9 — Tests Pass

**Result: PASS**

All 12 feature tests pass with 46 assertions. No tests are marked `->skip()` or `->todo()`.

```
Tests:    12 passed (46 assertions)
Duration: 0.58s
```

Coverage includes: program creation, nested validation, scoped uniqueness, ownership (403 vs 404), exercise delete renumbering, reorder upward, reorder downward, and soft-delete cascade.

---

## Criterion 10 — No Hardcoded Environment Values

**Result: FAIL**

`.env.example` has `APP_DEBUG=true` on line 4. A developer who copies this verbatim to `.env` in a production-like environment will deploy with debug mode on, exposing stack traces and request data in HTTP error responses.

`.env` is correctly listed in `.gitignore` and is not tracked. No credentials, API keys, or secrets appear in any tracked file.

**Fix:** Change `.env.example` line 4 to `APP_DEBUG=false`.

---

## Summary

| # | Criterion | Result |
| --- | --- | --- |
| 1 | Type Safety (`declare(strict_types=1)`) | **FAIL** |
| 2 | Error Handling | **PASS** |
| 3 | Observability (logging on state changes) | **FAIL** |
| 4 | Configuration (no magic numbers) | **FAIL** |
| 5 | Validation (no N+1 queries) | **PASS** |
| 6 | Data Integrity (transactions, locks) | **PASS** |
| 7 | Security (auth, debug, stack traces) | **PASS** |
| 8 | API Consistency (resources, status codes) | **FAIL** |
| 9 | Tests Pass | **PASS** |
| 10 | No Hardcoded Environment Values | **FAIL** |

## Priority order for fixes

1. `APP_DEBUG=false` in `.env.example` — one-line change, high production risk if missed
2. `declare(strict_types=1)` in all 12 `app/` files — mechanical, no logic changes
3. Logging on all 6 state-changing actions — required for incident investigation
4. Extract magic numbers to `config/workout.php` — unblocks per-environment tuning
5. API Resources — controls response shape and prevents accidental attribute leakage
