<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantRateLimiter
{
    private const PLAN_LIMITS = [
        'free' => 100,       // requests per minute
        'basic' => 300,
        'pro' => 1000,
        'enterprise' => 5000,
    ];

    public function __construct(
        private RateLimiter $limiter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->tenant_id) {
            return $next($request); // no tenant = no tenant rate limit
        }

        $tenant = $user->tenant;

        if (! $tenant) {
            return $next($request);
        }

        $key = "tenant:{$tenant->id}";
        $maxAttempts = self::PLAN_LIMITS[$tenant->plan] ?? self::PLAN_LIMITS['free'];

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            return response()->json([
                'message' => 'API rate limit exceeded for your plan.',
                'retry_after_seconds' => $retryAfter,
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => $retryAfter,
            ]);
        }

        $this->limiter->hit($key, 60);

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $maxAttempts - $this->limiter->attempts($key)),
        ]);
    }
}
