<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json([
            'summary' => $this->reportService->getSummary(
                $request->from,
                $request->to,
            ),
        ]);
    }

    public function jobsByStatus(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json([
            'statuses' => $this->reportService->getJobsByStatus(
                $request->from,
                $request->to,
            ),
        ]);
    }

    public function jobsByDate(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        return response()->json([
            'dates' => $this->reportService->getJobsByDate(
                $request->from,
                $request->to,
            ),
        ]);
    }

    public function technicianPerformance(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json([
            'technicians' => $this->reportService->getTechnicianPerformance(
                $request->from,
                $request->to,
            ),
        ]);
    }
}
