<?php
// app/Http/Middleware/ApiResponseHandler.php

namespace App\Http\Middleware;

use App\Helper\ApiResponseHelper;
use Closure;
use Illuminate\Http\Response;

class ApiResponseHandler
{
    public function handle($request, Closure $next)
    {
        $response = $next($request)
        ->header("Access-Control-Allow-Origin", "*")
        ->header("Access-Control-Allow-Methods", "*")
        ->header("Access-Control-Allow-Headers", "*");

        // Check if the response is a JSON response
        if ($response->headers->get('Content-Type') == 'application/json') {
            // Get the original response data
            $data = $response->original;

            // Check if the response contains an "error" key
            $isError = isset($data['errors']);

            // If it's an error, use the helper function to format errors
            $formattedErrors = $isError ? $data['errors'] : null;
            // Add a consistent structure to the response
            $formattedResponse = [
                'errors' => $formattedErrors,
                'message' => $isError ? 'Error' : 'Success',
                'status_code' => $response->getStatusCode(),
                'payload' => $isError ? null : ['data' => $data],
            ];

            // Update the response with the formatted data
            $response->setContent(json_encode($formattedResponse));
        }

        return $response;
    }
}
