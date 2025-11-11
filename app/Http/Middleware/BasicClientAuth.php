<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\ClientDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class BasicClientAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = $request->getUser();
        $password = $request->getPassword();

        if (empty($username) || empty($password)) {
            return response()->json(['message' => 'Missing credentials'], 401, [
                'WWW-Authenticate' => 'Basic realm="Restricted Area"',
            ]);
        }

        $client = DB::table('client_domains')->where('username', $username)
            ->where('password', $password)
            ->first();

        if (! $client) {
            return response()->json(['message' => 'Unauthorized'], 401, [
                'WWW-Authenticate' => 'Basic realm="Restricted Area"',
            ]);
        }

        // Optionally store authenticated client for later use
        $request->attributes->set('client_domain', $client);

        return $next($request);
    }
}
