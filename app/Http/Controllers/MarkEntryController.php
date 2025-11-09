<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ApiResponseHelper;
use Illuminate\Support\Facades\DB;
use App\Jobs\CalculateExamMarksJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MarkEntryController extends Controller
{
    public function calculate(Request $request)
    {
        Log::channel('exam_flex_log')->info('Mark Calculation Request', [
            'request' => $request->all()
        ]);

        $username = $request->getUser();
        $password = $request->getPassword();

        // Hash::check($password, $client->password_hash)

        $credentials = [
            'username' => $username,
            'password' => $password,
        ];

        Log::channel('exam_flex_log')->info('Mark Calculation Credentials', [
            'credentials' => $credentials
        ]);
        if (!$credentials || !isset($credentials['username']) || !isset($credentials['password'])) {
            return response()->json(['error' => 'Unauthorized Credentials'], 401);
        }
        $domainRecord = DB::table('client_domains')->where('username', $credentials['username'])
            // ->where('password_hash', $credentials['password'])
            ->first();

        if (!$domainRecord || !Hash::check($credentials['password'], $domainRecord->password_hash)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'institute_id' => 'required',
            'exam_type' => 'nullable|string',
            'exam_name' => 'required|string',
            'subject_name' => 'required|string',
            'exam_config' => 'required',
            'students' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => ApiResponseHelper::formatErrors(ApiResponseHelper::VALIDATION_ERROR, $validator->errors()->toArray()),
                'payload' => null,
            ], 422);
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
