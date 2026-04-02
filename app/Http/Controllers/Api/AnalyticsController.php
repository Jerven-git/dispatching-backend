<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService,
    ) {}

    public function revenueTrend(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json([
            'trend' => $this->analyticsService->getRevenueTrend(
                $request->from,
                $request->to,
            ),
        ]);
    }

    public function jobTrend(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json([
            'trend' => $this->analyticsService->getJobTrend(
                $request->from,
                $request->to,
            ),
        ]);
    }

    public function servicePopularity(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json([
            'services' => $this->analyticsService->getServicePopularity(
                $request->from,
                $request->to,
            ),
        ]);
    }

    public function customerLifetimeValue(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'customers' => $this->analyticsService->getCustomerLifetimeValue(
                $request->integer('limit', 20),
            ),
        ]);
    }

    public function jobProfitability(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'jobs' => $this->analyticsService->getJobProfitability(
                $request->from,
                $request->to,
                $request->integer('limit', 50),
            ),
            'summary' => $this->analyticsService->getProfitabilitySummary(
                $request->from,
                $request->to,
            ),
        ]);
    }
}
