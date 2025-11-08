<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use App\Services\ExamMarkCalculator;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CalculateExamMarksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 2;

    protected $payload;
    protected $instituteId;
    protected $instituteDetailsId;
    protected $context;

    public function __construct($payload, $instituteId, $instituteDetailsId, $context)
    {
        $this->payload = $payload;
        $this->instituteId = $instituteId;
        $this->instituteDetailsId = $instituteDetailsId;
        $this->context = $context;
    }

    public function handle()
    {
        $calculator = new ExamMarkCalculator();
        $results = $calculator->calculate($this->payload);

        $this->saveToInstitute($results);
    }

    private function saveToInstitute($results)
    {
        $url = config('services.institute.url') . '/api/marks/store-async';

        $response = Http::withHeaders([
            'X-Institute-ID' => $this->instituteId,
            'X-Institute-Token' => config('services.institute.token'),
            'X-Institute-Details-ID' => $this->instituteDetailsId,
            'X-Academic-Year-ID' => $this->context['academic_year_id'],
            'X-Department-ID' => $this->context['department_id'],
            'X-Combinations-Pivot-ID' => $this->context['combinations_pivot_id'],
            'X-Exam-ID' => $this->context['exam_id'],
            'X-Subject-ID' => $this->context['subject_id'],
            'Content-Type' => 'application/json',
        ])->post($url, [
            'job_id' => $this->job->getJobId(),
            'results' => $results
        ]);

        if (!$response->successful()) {
            Log::error('storeAsync failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            $this->fail();
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Exam Calculation Job Failed', [
            'institute' => $this->instituteId,
            'error' => $exception->getMessage()
        ]);
    }
}
