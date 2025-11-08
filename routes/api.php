
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MarkEntryController;

Route::middleware('exam.system')->post('/process', [MarkEntryController::class, 'calculate']);
