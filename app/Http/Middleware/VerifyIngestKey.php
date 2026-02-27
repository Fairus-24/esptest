<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIngestKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = trim((string) config('http_server.ingest_key', ''));
        $allowWithoutKey = (bool) config('http_server.allow_ingest_without_key', false);

        if ($expectedKey === '') {
            if ($allowWithoutKey || app()->environment(['local', 'testing'])) {
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'HTTP ingest key is not configured on server.',
            ], 503);
        }

        $providedKey = trim((string) $request->header('X-Ingest-Key', ''));
        if ($providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized ingest key.',
            ], 401);
        }

        return $next($request);
    }
}
