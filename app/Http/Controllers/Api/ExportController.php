<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    public function __construct(
        private ExportService $exportService,
    ) {}

    public function jobs(Request $request): Response
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
        ]);

        return $this->exportService->exportJobs(
            $request->from,
            $request->to,
            $request->status,
        );
    }

    public function invoices(Request $request): Response
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
        ]);

        return $this->exportService->exportInvoices(
            $request->from,
            $request->to,
            $request->status,
        );
    }

    public function customers(): Response
    {
        return $this->exportService->exportCustomers();
    }

    public function technicianPerformance(Request $request): Response
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return $this->exportService->exportTechnicianPerformance(
            $request->from,
            $request->to,
        );
    }
}
