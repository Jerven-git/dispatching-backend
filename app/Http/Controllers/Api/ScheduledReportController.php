<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduledReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduledReportController extends Controller
{
    public function index(): JsonResponse
    {
        $reports = ScheduledReport::with('creator:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['scheduled_reports' => $reports]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'report_type' => ['required', 'in:summary,jobs_by_status,jobs_by_date,technician_performance,customer_lifetime_value,job_profitability'],
            'frequency' => ['required', 'in:daily,weekly,monthly'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['required', 'email'],
            'parameters' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['created_by'] = $request->user()->id;

        $report = ScheduledReport::create($data);
        $report->load('creator:id,name');

        return response()->json([
            'message' => 'Scheduled report created.',
            'scheduled_report' => $report,
        ], 201);
    }

    public function show(ScheduledReport $scheduledReport): JsonResponse
    {
        $scheduledReport->load('creator:id,name');

        return response()->json([
            'scheduled_report' => $scheduledReport,
        ]);
    }

    public function update(Request $request, ScheduledReport $scheduledReport): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'report_type' => ['sometimes', 'in:summary,jobs_by_status,jobs_by_date,technician_performance,customer_lifetime_value,job_profitability'],
            'frequency' => ['sometimes', 'in:daily,weekly,monthly'],
            'recipients' => ['sometimes', 'array', 'min:1'],
            'recipients.*' => ['required', 'email'],
            'parameters' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $scheduledReport->update($data);

        return response()->json([
            'message' => 'Scheduled report updated.',
            'scheduled_report' => $scheduledReport,
        ]);
    }

    public function destroy(ScheduledReport $scheduledReport): JsonResponse
    {
        $scheduledReport->delete();

        return response()->json([
            'message' => 'Scheduled report deleted.',
        ]);
    }
}
