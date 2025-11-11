
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MarkEntryController;

// routes/api.php
Route::post('/mark-entry/config', [MarkEntryController::class, 'storeConfig'])->middleware('client.basic');
Route::post('/mark-entry/process', [MarkEntryController::class, 'processStudents']);
Route::post('result-process', [MarkEntryController::class, 'resultProcess']);
