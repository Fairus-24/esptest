<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessionKey = (string) config('admin.session_key', 'admin_config_authenticated');
        $ttlMinutes = max(15, (int) config('admin.session_ttl_minutes', 240));
        $payload = $request->session()->get($sessionKey);

        $authenticated = is_array($payload) && ($payload['ok'] ?? false) === true;
        if (!$authenticated) {
            return $this->unauthorizedResponse($request);
        }

        $loginAt = (int) ($payload['at'] ?? 0);
        if ($loginAt > 0) {
            $ageSeconds = now()->timestamp - $loginAt;
            if ($ageSeconds > ($ttlMinutes * 60)) {
                $request->session()->forget($sessionKey);
                return $this->unauthorizedResponse($request, 'Sesi admin kedaluwarsa. Silakan login ulang.');
            }
        }

        return $next($request);
    }

    private function unauthorizedResponse(Request $request, ?string $message = null): Response
    {
        $message = $message ?? 'Akses admin memerlukan login Google.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 401);
        }

        return redirect()
            ->route('admin.login')
            ->with('admin_error', $message);
    }
}
