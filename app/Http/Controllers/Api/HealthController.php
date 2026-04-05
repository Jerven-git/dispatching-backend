<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'services' => [],
        ];

        // Database check
        try {
            DB::connection()->getPdo();
            $checks['services']['database'] = 'ok';
        } catch (\Throwable) {
            $checks['services']['database'] = 'error';
            $checks['status'] = 'degraded';
        }

        // Redis check
        try {
            Redis::ping();
            $checks['services']['redis'] = 'ok';
        } catch (\Throwable) {
            $checks['services']['redis'] = 'error';
            $checks['status'] = 'degraded';
        }

        $statusCode = $checks['status'] === 'ok' ? 200 : 503;

        return response()->json($checks, $statusCode);
    }
}
