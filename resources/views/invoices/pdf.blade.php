<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 14px; color: #333; padding: 40px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .company { font-size: 24px; font-weight: bold; color: #4f46e5; }
        .invoice-title { font-size: 28px; font-weight: bold; color: #111; text-align: right; }
        .invoice-number { font-size: 14px; color: #666; text-align: right; margin-top: 4px; }
        .meta-row { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .meta-block { width: 48%; }
        .meta-block h3 { font-size: 12px; text-transform: uppercase; color: #888; margin-bottom: 8px; letter-spacing: 0.5px; }
        .meta-block p { margin-bottom: 2px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        thead th { background: #f3f4f6; padding: 10px 12px; text-align: left; font-size: 12px; text-transform: uppercase; color: #666; border-bottom: 2px solid #e5e7eb; }
        tbody td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
        .text-right { text-align: right; }
        .totals { width: 300px; margin-left: auto; }
        .totals table { margin-bottom: 0; }
        .totals td { padding: 6px 12px; border: none; }
        .totals .total-row td { font-size: 18px; font-weight: bold; border-top: 2px solid #333; padding-top: 10px; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .status-draft { background: #fef3c7; color: #92400e; }
        .status-sent { background: #dbeafe; color: #1e40af; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .notes { margin-top: 30px; padding: 16px; background: #f9fafb; border-radius: 8px; }
        .notes h3 { font-size: 12px; text-transform: uppercase; color: #888; margin-bottom: 8px; }
        .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <table style="width:100%; margin-bottom: 30px; border: none;">
        <tr>
            <td style="border: none; padding: 0;">
                <div class="company">Dispatch</div>
            </td>
            <td style="border: none; padding: 0; text-align: right;">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number">{{ $invoice->invoice_number }}</div>
            </td>
        </tr>
    </table>

    <table style="width:100%; margin-bottom: 30px; border: none;">
        <tr>
            <td style="width:50%; border: none; padding: 0; vertical-align: top;">
                <h3 style="font-size:12px; text-transform:uppercase; color:#888; margin-bottom:8px;">Bill To</h3>
                <p style="font-weight:bold;">{{ $invoice->customer->name }}</p>
                @if($invoice->customer->email)<p>{{ $invoice->customer->email }}</p>@endif
                @if($invoice->customer->phone)<p>{{ $invoice->customer->phone }}</p>@endif
                @if($invoice->customer->address)<p>{{ $invoice->customer->address }}</p>@endif
                @if($invoice->customer->city || $invoice->customer->state)
                    <p>{{ $invoice->customer->city }}{{ $invoice->customer->state ? ', '.$invoice->customer->state : '' }} {{ $invoice->customer->zip_code }}</p>
                @endif
            </td>
            <td style="width:50%; border: none; padding: 0; vertical-align: top; text-align: right;">
                <h3 style="font-size:12px; text-transform:uppercase; color:#888; margin-bottom:8px;">Invoice Details</h3>
                <p><strong>Date:</strong> {{ $invoice->issued_date->format('M d, Y') }}</p>
                <p><strong>Due:</strong> {{ $invoice->due_date->format('M d, Y') }}</p>
                <p><strong>Status:</strong>
                    <span class="status-badge status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span>
                </p>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Reference</th>
                <th>Service</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    {{ $invoice->serviceJob->description ?? $invoice->serviceJob->service->name }}
                    @if($invoice->serviceJob->address)
                        <br><small style="color:#888;">{{ $invoice->serviceJob->address }}</small>
                    @endif
                    @if($invoice->serviceJob->scheduled_date)
                        <br><small style="color:#888;">Date: {{ $invoice->serviceJob->scheduled_date->format('M d, Y') }}</small>
                    @endif
                </td>
                <td>{{ $invoice->serviceJob->reference_number }}</td>
                <td>{{ $invoice->serviceJob->service->name }}</td>
                <td class="text-right">${{ number_format((float) $invoice->subtotal, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>Subtotal</td>
                <td class="text-right">${{ number_format((float) $invoice->subtotal, 2) }}</td>
            </tr>
            @if((float) $invoice->tax_rate > 0)
            <tr>
                <td>Tax ({{ $invoice->tax_rate }}%)</td>
                <td class="text-right">${{ number_format((float) $invoice->tax_amount, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Total</td>
                <td class="text-right">${{ number_format((float) $invoice->total, 2) }}</td>
            </tr>
        </table>
    </div>

    @if($invoice->notes)
    <div class="notes">
        <h3>Notes</h3>
        <p>{{ $invoice->notes }}</p>
    </div>
    @endif

    <div class="footer">
        <p>Thank you for your business!</p>
    </div>
</body>
</html>
