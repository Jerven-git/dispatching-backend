<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceJob;
use Illuminate\Http\Response;

class ExportService
{
    /**
     * Export jobs as CSV.
     */
    public function exportJobs(?string $from = null, ?string $to = null, ?string $status = null): Response
    {
        $query = ServiceJob::with(['customer:id,name', 'service:id,name', 'technician:id,name']);

        if ($from) {
            $query->where('scheduled_date', '>=', $from);
        }
        if ($to) {
            $query->where('scheduled_date', '<=', $to);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $jobs = $query->orderByDesc('scheduled_date')->get();

        $headers = ['Reference', 'Customer', 'Service', 'Technician', 'Status', 'Priority', 'Scheduled Date', 'Scheduled Time', 'Address', 'Total Cost', 'Created At'];

        $rows = $jobs->map(fn ($job) => [
            $job->reference_number,
            $job->customer?->name ?? '',
            $job->service?->name ?? '',
            $job->technician?->name ?? '',
            $job->status,
            $job->priority,
            $job->scheduled_date?->format('Y-m-d'),
            $job->scheduled_time,
            $job->address,
            $job->total_cost,
            $job->created_at?->format('Y-m-d H:i'),
        ]);

        return $this->buildCsv("jobs-export", $headers, $rows->all());
    }

    /**
     * Export invoices as CSV.
     */
    public function exportInvoices(?string $from = null, ?string $to = null, ?string $status = null): Response
    {
        $query = Invoice::with(['customer:id,name', 'serviceJob:id,reference_number']);

        if ($from) {
            $query->where('issued_date', '>=', $from);
        }
        if ($to) {
            $query->where('issued_date', '<=', $to);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $invoices = $query->orderByDesc('issued_date')->get();

        $headers = ['Invoice #', 'Customer', 'Job Reference', 'Status', 'Subtotal', 'Tax Rate', 'Tax Amount', 'Total', 'Issued Date', 'Due Date', 'Paid At'];

        $rows = $invoices->map(fn ($inv) => [
            $inv->invoice_number,
            $inv->customer?->name ?? '',
            $inv->serviceJob?->reference_number ?? '',
            $inv->status,
            $inv->subtotal,
            $inv->tax_rate . '%',
            $inv->tax_amount,
            $inv->total,
            $inv->issued_date?->format('Y-m-d'),
            $inv->due_date?->format('Y-m-d'),
            $inv->paid_at?->format('Y-m-d H:i'),
        ]);

        return $this->buildCsv("invoices-export", $headers, $rows->all());
    }

    /**
     * Export customers as CSV.
     */
    public function exportCustomers(): Response
    {
        $customers = Customer::withCount(['serviceJobs', 'invoices'])
            ->orderBy('name')
            ->get();

        $headers = ['Name', 'Email', 'Phone', 'Address', 'City', 'State', 'Zip', 'Total Jobs', 'Total Invoices', 'Portal Access', 'Created At'];

        $rows = $customers->map(fn ($c) => [
            $c->name,
            $c->email,
            $c->phone,
            $c->address,
            $c->city,
            $c->state,
            $c->zip_code,
            $c->service_jobs_count,
            $c->invoices_count,
            $c->portal_access ? 'Yes' : 'No',
            $c->created_at?->format('Y-m-d'),
        ]);

        return $this->buildCsv("customers-export", $headers, $rows->all());
    }

    /**
     * Export technician performance as CSV.
     */
    public function exportTechnicianPerformance(?string $from = null, ?string $to = null): Response
    {
        $reportService = app(ReportService::class);
        $data = $reportService->getTechnicianPerformance($from, $to);

        $headers = ['Name', 'Email', 'Phone', 'Completed Jobs', 'Total Revenue', 'Avg Duration (min)'];

        $rows = array_map(fn ($row) => [
            $row['technician']['name'],
            $row['technician']['email'],
            $row['technician']['phone'],
            $row['completed_jobs'],
            $row['total_revenue'],
            $row['avg_duration_minutes'] ?? 'N/A',
        ], $data);

        return $this->buildCsv("technician-performance", $headers, $rows);
    }

    /**
     * Build CSV response from headers and rows.
     */
    private function buildCsv(string $filename, array $headers, array $rows): Response
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $date = now()->format('Y-m-d');

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}-{$date}.csv\"",
        ]);
    }
}
