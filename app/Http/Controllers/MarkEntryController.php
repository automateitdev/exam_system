<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Jobs\CalculateExamMarksJob;
use Illuminate\Support\Facades\Log;

class MarkEntryController extends Controller
{
    public function calculate(Request $request)
    {
        Log::channel('exam_flex_log')->info('Mark Calculation Request', [
            'request' => $request->all()
        ]);
        
        $credentials = $request->getUserPass();

        $domainRecord = DB::table('client_domains')->where('username', $credentials['username'])
            ->where('password', $credentials['password'])
            ->first();

        if (!$domainRecord) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Dispatch Job
        $job = CalculateExamMarksJob::dispatch(
            $request->all(),
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
