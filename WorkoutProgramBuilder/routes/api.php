<?php

use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\ProgramExerciseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/programs', [ProgramController::class, 'index']);
    Route::post('/programs', [ProgramController::class, 'store']);
    Route::get('/programs/{program}', [ProgramController::class, 'show']);
    Route::put('/programs/{program}', [ProgramController::class, 'update']);
    Route::delete('/programs/{program}', [ProgramController::class, 'destroy']);

    Route::post('/programs/{program}/days/{day}/exercises', [ProgramExerciseController::class, 'store']);
    Route::delete('/programs/{program}/days/{day}/exercises/{exercise}', [ProgramExerciseController::class, 'destroy']);
    Route::patch('/programs/{program}/days/{day}/exercises/{exercise}/reorder', [ProgramExerciseController::class, 'reorder']);
});
