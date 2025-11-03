<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExamCalculation;
use App\Helpers\ApiResponseHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MarkEntryController extends Controller
{
    public function calculate(Request $request)
    {
        try {
            $instituteId = $request->attributes->get('institute_id');

            $validator = Validator::make($request->all(), [
                'exam_type' => 'required|in:semester',
                'exam_name' => 'required|string',
                'subject_name' => 'required|string',
                'exam_config' => 'required|array',
                'exam_config.details' => 'required|array',
                'exam_config.grace_mark' => 'nullable|numeric',
                'exam_config.overall_pass_mark' => 'nullable|numeric',
                'exam_config.method_of_evaluation' => 'nullable|string',
                'exam_config.attendance_required' => 'nullable|boolean',
                'exam_config.grade_threshold.highest_fail_mark' => 'nullable|numeric',
                'exam_config.grade_points' => 'required|array',
                'students' => 'required|array|min:1',
                'students.*.17' => 'required|integer',
                'students.*.part_marks' => 'required|array',
                'students.*.attendance_status' => 'nullable|string|in:present,absent',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => ApiResponseHelper::formatErrors(ApiResponseHelper::VALIDATION_ERROR, $validator->errors()->toArray()),
                    'payload' => null,
                ], 422);
            }

            $results = [];

            foreach ($request->students as $student) {
                $results[] = $this->calculateStudent($student, $request->exam_config);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Calculation completed',
                'data' => $results
            ], 200);
        } catch (\Exception $e) {
            Log::error('Exam Calculation Failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Calculation failed'
            ], 500);
        }
    }

    private function calculateStudent($student, $config)
    {
        $studentId = $student['student_id'];
        $partMarks = $student['part_marks'];
        $isAbsent = ($config['attendance_required'] ?? false) &&
            (strtolower($student['attendance_status'] ?? 'absent') === 'absent');

        $examName = $config['exam_name'] ?? 'Semester Exam';
        $subjectName = $config['subject_name'] ?? null;

        if ($isAbsent) {
            return [
                'student_id' => $studentId,
                'obtained_mark' => 0,
                'final_mark' => 0,
                'grace_mark' => 0,
                'result_status' => 'Fail',
                'remark' => 'Absent',
                'part_marks' => $partMarks,
                'exam_name' => $examName,
                'subject_name' => $subjectName,
                'grade' => 'F',
                'grade_point' => '0.00'
            ];
        }

        $details = collect($config['details']);
        $method = $config['method_of_evaluation'] ?? 'At Actual';
        $graceMark = $config['grace_mark'] ?? 0;

        // 1. Obtained Mark
        $calculated = 0;
        foreach ($details as $d) {
            $code = $d['exam_code_title'];
            $mark = $partMarks[$code] ?? 0;
            $total = $d['total_mark'] ?? 100;
            $conv = $d['conversion'] ?? 100;
            $calculated += ($mark / $total) * $conv;
        }
        $obtainedMark = roundMark($calculated, $method);

        // 2. Individual Pass
        $individualPass = true;
        foreach ($details->where('is_individual', true) as $d) {
            $code = $d['exam_code_title'];
            $mark = $partMarks[$code] ?? 0;
            $total = $d['total_mark'] ?? 100;
            $conv = $d['conversion'] ?? 100;
            $converted = ($mark / $total) * $conv;
            if (roundMark($converted, $method) < ($d['pass_mark'] ?? 0)) {
                $individualPass = false;
                break;
            }
        }

        // 3. Overall Pass
        $overallPass = true;
        $overallPassMark = $config['overall_pass_mark'] ?? null;
        if ($overallPassMark !== null) {
            $calc = 0;
            foreach ($details->where('is_overall', true) as $d) {
                $code = $d['exam_code_title'];
                $mark = $partMarks[$code] ?? 0;
                $total = $d['total_mark'] ?? 100;
                $conv = $d['conversion'] ?? 100;
                $calc += ($mark / $total) * $conv;
            }
            $overallPass = roundMark($calc, $method) >= $overallPassMark;
        }

        // 4. Fail Threshold
        $failThreshold = 33;
        $highestFail = $config['grade_threshold']['highest_fail_mark'] ?? null;
        if ($highestFail !== null) $failThreshold = $highestFail + 0.01;

        $finalMark = $obtainedMark;
        $pass = $individualPass && $overallPass && ($finalMark >= $failThreshold);
        $remark = $pass ? '' : (
            $individualPass
            ? ($overallPass ? 'Below Threshold' : 'Failed Overall')
            : 'Failed Individual'
        );

        // 5. Grace
        $appliedGrace = 0;
        if (!$pass && $graceMark > 0 && $finalMark < $failThreshold) {
            $needed = ceil($failThreshold - $finalMark);
            $appliedGrace = min($needed, $graceMark);
            $finalMark += $appliedGrace;
            if ($finalMark >= $failThreshold) {
                $pass = true;
                $remark = "Pass by Grace ($appliedGrace marks)";
            }
        }

        // 6. Grade
        $gradePoints = $config['grade_points'];
        $studentGrade = collect($gradePoints)->first(fn($g) => $finalMark >= $g['from_mark'] && $finalMark <= $g['to_mark']);
        $grade = $studentGrade['grade'] ?? 'F';
        $gradePoint = $studentGrade['grade_point'] ?? '0.00';

        return [
            'student_id' => $studentId,
            'obtained_mark' => (float) $obtainedMark,
            'final_mark' => (float) $finalMark,
            'grace_mark' => (float) $appliedGrace,
            'result_status' => $pass ? 'Pass' : 'Fail',
            'remark' => $remark,
            'part_marks' => $partMarks,
            'exam_name' => $examName,
            'subject_name' => $subjectName,
            'grade' => $grade,
            'grade_point' => $gradePoint,
            'attendance_status' => $student['attendance_status'] ?? null
        ];
    }
}
