<?php

namespace App\Http\Middleware;

use App\Support\SignedRequest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets through only requests Rizq signed with the shared secret. Until the
 * secret is set the internal API answers nobody.
 */
class VerifyRizqSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SignedRequest::verify($request, (string) config('rizq.secret'))) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        return $next($request);
    }
}
