<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Jobs\CalculateExamMarksJob;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class MarkEntryController extends Controller
{
    public function calculate(Request $request)
    {
        Log::channel('exam_flex_log')->info('Mark Calculation Request', [
            'request' => $request->all()
        ]);

        $username = $request->getUser();
        $password = $request->getPassword();
        $credentials = [
            'username' => $username,
            'password' => Hash::make($password), //$password
        ];

        Log::channel('exam_flex_log')->info('Mark Calculation Credentials', [
            'credentials' => $credentials
        ]);
        if (!$credentials || !isset($credentials['username']) || !isset($credentials['password'])) {
            return response()->json(['error' => 'Unauthorized Credentials'], 401);
        }
        $domainRecord = DB::table('client_domains')->where('username', $credentials['username'])
            ->where('password_hash', $credentials['password'])
            ->first();

        if (!$domainRecord) {
            return response()->json(['error' => 'Unauthorized domain'], 401);
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
