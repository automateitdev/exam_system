<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\TempExamConfig;
use App\Helpers\ApiResponseHelper;
use App\Services\ResultCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\ExamMarkCalculator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MarkEntryController extends Controller
{
    protected $examMarkCalculator;

    public function __construct(ExamMarkCalculator $examMarkCalculator)
    {
        $this->examMarkCalculator = $examMarkCalculator;
    }
    public function storeConfig(Request $request)
    {
        Log::channel('exam_flex_log')->info('Mark Entry Config Request', [
            'request' => $request->all()
        ]);
        $username = $request->getUser();
        $password = $request->getPassword();

        $client = DB::table('client_domains')
            ->where('username', $username)
            ->first();

        if (!$client || !Hash::check($password, $client->password_hash)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'institute_id' => 'required',
            'subjects' => 'required|array',
            'grade_points' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tempId = 'temp_' . Str::random(12);

        Log::channel('exam_flex_log')->info('Generated Temp ID for Config', [
            'temp_id' => $tempId
        ]);
        TempExamConfig::create([
            'temp_id' => $tempId,
            'institute_id' => $request->institute_id,
            'config' => $request->all(),
            'expires_at' => now()->addHours(2),
        ]);

        Log::channel('exam_flex_log')->info('Mark Entry Config Stored', [
            'temp_id' => $tempId,
            'expires_at' => now()->addHours(2)->toDateTimeString()
        ]);
        return response()->json([
            'status' => 'config_saved',
            'temp_id' => $tempId,
            'expires_at' => now()->addHours(2)->toDateTimeString()
        ], 202);
    }

    public function processStudents(Request $request)
    {
        Log::channel('exam_flex_log')->info('Mark Calculation Request', [
            'request' => $request->all()
        ]);
        $username = $request->getUser();
        $password = $request->getPassword();

        $client = DB::table('client_domains')
            ->where('username', $username)
            ->first();

        if (!$client || !Hash::check($password, $client->password_hash)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'temp_id' => 'required',
            'students' => 'required|array|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $temp = TempExamConfig::where('temp_id', $request->temp_id)
            ->where('expires_at', '>', now())
            ->first();

        Log::channel('exam_flex_log')->info('Fetched Temp Config for Processing', [
            'temp_id' => $request->temp_id,
            'temp_exists' => $temp !== null
        ]);
        if (!$temp) {
            return response()->json(['error' => 'Config expired or invalid'], 410);
        }

        $config = json_decode($temp->config, true);
        $fullPayload = array_merge($config, ['students' => $request->students]);

        Log::channel('exam_flex_log')->info('Mark Calculation Payload', [
            'payload' => $fullPayload
        ]);
        // Calculate marks (synchronous)
        $results = $this->examMarkCalculator->calculate($fullPayload);

        Log::channel('exam_flex_log')->info('Mark Calculation Result', [
            'results' => $results
        ]);
        // Clean up
        Log::channel('exam_flex_log')->info('Deleted Temp Config after Processing', [
            'temp_id' => $request->temp_id
        ]);
        $temp->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Marks calculated and ready to save',
            'results' => $results
        ], 200);
    }
    // public function processStudents(Request $request)
    // {
    //     Log::channel('exam_flex_log')->info('Mark Calculation Request', [
    //         'request' => $request->all()
    //     ]);

    //     $username = $request->getUser();
    //     $password = $request->getPassword();

    //     // Hash::check($password, $client->password_hash)

    //     $credentials = [
    //         'username' => $username,
    //         'password' => $password,
    //     ];

    //     Log::channel('exam_flex_log')->info('Mark Calculation Credentials', [
    //         'credentials' => $credentials
    //     ]);
    //     if (!$credentials || !isset($credentials['username']) || !isset($credentials['password'])) {
    //         return response()->json(['error' => 'Unauthorized Credentials'], 401);
    //     }
    //     $domainRecord = DB::table('client_domains')->where('username', $credentials['username'])
    //         // ->where('password_hash', $credentials['password'])
    //         ->first();

    //     if (!$domainRecord || !Hash::check($credentials['password'], $domainRecord->password_hash)) {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'institute_id' => 'required',
    //         'exam_type' => 'nullable|string',
    //         'exam_name' => 'required|string',
    //         'subject_name' => 'required|string',
    //         'exam_config' => 'required',
    //         'students' => 'required',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'errors' => ApiResponseHelper::formatErrors(ApiResponseHelper::VALIDATION_ERROR, $validator->errors()->toArray()),
    //             'payload' => null,
    //         ], 422);
    //     }

    //     $results = $this->examMarkCalculator->calculate($request->all());
    //     //

    //     Log::channel('exam_flex_log')->info('Mark Calculation Result', [
    //         'results' => $results
    //     ]);
    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Marks Calculated Successfully',
    //         'results' => $results
    //     ], 202);
    // }

    //result process
    public function resultProcess(Request $request)
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

        $resultCalculator = app(ResultCalculator::class);

        $results = $resultCalculator->calculate($request->all());
        // $results = $this->resultProcess->calculate($request->all());
        //

        Log::channel('exam_flex_log')->info('Mark Calculation Result', [
            'results' => $results
        ]);
        return response()->json([
            'status' => 'success',
            'message' => 'Marks Calculated Successfully',
            'results' => $results
        ], 202);
    }
}
