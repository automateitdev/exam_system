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

    public function __construct($payload)
    {
        $this->payload = $payload;
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
            'institute' => $this->payload['institute_id'],
            'error' => $exception->getMessage()
        ]);
    }
}
