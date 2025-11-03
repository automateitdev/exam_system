<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class VerifyExamSystemRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $instituteId = $request->header('X-Institute-ID');
        $token = $request->header('X-Institute-Token');

        // 1. Token Check
        if ($token !== config('services.exam_system.token')) {
            return response()->json([
                'error' => 'Invalid token'
            ], 401);
        }

        // 2. Institute ID Required
        if (!$instituteId) {
            return response()->json([
                'error' => 'X-Institute-ID header missing'
            ], 400);
        }

        // 3. Rate Limiting (per institute)
        $key = 'exam_calc_' . $instituteId;
        if (RateLimiter::tooManyAttempts($key, 60)) { // 60 req/min
            return response()->json([
                'error' => 'Too many requests'
            ], 429);
        }

        RateLimiter::hit($key, 60); // 1 minute

        // 4. Optional: Institute Exists in DB?
        // if (!Institute::where('id', $instituteId)->exists()) {
        //     return response()->json(['error' => 'Invalid institute'], 403);
        // }

        // 5. Pass institute_id to controller
        $request->attributes->set('institute_id', $instituteId);

        return $next($request);
    }
}
