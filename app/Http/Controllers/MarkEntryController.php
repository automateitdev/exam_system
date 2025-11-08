<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\CalculateExamMarksJob;
use Illuminate\Support\Facades\Log;

class MarkEntryController extends Controller
{
    // MarkCalculationController.php
    public function calculate(Request $request)
    {
        Log::channel('exam_flex_log')->info('Mark Calculation Request', [
            'request' => $request->all()
        ]);
        $instituteId = $request->attributes->get('institute_id');

        // Dispatch Job
        $job = CalculateExamMarksJob::dispatch(
            $request->all(),
            $instituteId,
            $request->header('X-Institute-Details-ID'),
            $request->only([
                'academic_year_id',
                'department_id',
                'combinations_pivot_id',
                'exam_id',
                'subject_id'
            ])
        )->onQueue('exam_calculations');

        Log::channel('exam_flex_log')->info('Mark Calculation Job Dispatched', [
            'job_id' => $job->getJobId()
        ]);
        return response()->json([
            'status' => 'queued',
            'message' => 'Calculation started in background.',
            'job_id' => $job->getJobId()
        ], 202);
    }
}
