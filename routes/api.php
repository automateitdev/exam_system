
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MarkEntryController;

Route::post('/process', [MarkEntryController::class, 'calculate']);
