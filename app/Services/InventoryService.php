<?php

namespace App\Services;

use App\Models\JobPart;
use App\Models\Part;
use App\Models\ServiceJob;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Add a part to a job, deduct stock, and recalculate job total.
     */
    public function addPartToJob(ServiceJob $job, int $partId, int $quantity, int $addedBy, ?string $notes = null): JobPart
    {
        $part = Part::findOrFail($partId);

        if (! $part->is_active) {
            throw ValidationException::withMessages([
                'part_id' => 'This part is no longer active.',
            ]);
        }

        if ($part->stock_quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Insufficient stock. Available: {$part->stock_quantity} {$part->unit}(s).",
            ]);
        }

        $unitPrice = $part->unit_price;
        $totalPrice = $unitPrice * $quantity;

        $jobPart = JobPart::create([
            'service_job_id' => $job->id,
            'part_id' => $partId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'added_by' => $addedBy,
            'notes' => $notes,
        ]);

        // Deduct stock
        $part->decrement('stock_quantity', $quantity);

        // Recalculate job total
        $this->recalculateJobTotal($job);

        return $jobPart->load('part');
    }

    /**
     * Remove a part entry from a job, restore stock, and recalculate total.
     */
    public function removePartFromJob(JobPart $jobPart): void
    {
        $job = $jobPart->serviceJob;

        // Restore stock
        $jobPart->part->increment('stock_quantity', $jobPart->quantity);

        $jobPart->delete();

        $this->recalculateJobTotal($job);
    }

    /**
     * Update quantity of a part on a job, adjust stock, and recalculate total.
     */
    public function updateJobPartQuantity(JobPart $jobPart, int $newQuantity): JobPart
    {
        $part = $jobPart->part;
        $oldQuantity = $jobPart->quantity;
        $diff = $newQuantity - $oldQuantity;

        if ($diff > 0 && $part->stock_quantity < $diff) {
            throw ValidationException::withMessages([
                'quantity' => "Insufficient stock. Available: {$part->stock_quantity} {$part->unit}(s).",
            ]);
        }

        // Adjust stock
        if ($diff > 0) {
            $part->decrement('stock_quantity', $diff);
        } elseif ($diff < 0) {
            $part->increment('stock_quantity', abs($diff));
        }

        $jobPart->update([
            'quantity' => $newQuantity,
            'total_price' => $jobPart->unit_price * $newQuantity,
        ]);

        $this->recalculateJobTotal($jobPart->serviceJob);

        return $jobPart->fresh()->load('part');
    }

    /**
     * Recalculate job total_cost based on service base price + parts cost.
     */
    public function recalculateJobTotal(ServiceJob $job): void
    {
        $job->loadMissing('service');

        $servicePrice = $job->service?->base_price ?? 0;
        $partsCost = $job->parts()->sum('total_price');

        $job->update(['total_cost' => $servicePrice + $partsCost]);
    }

    /**
     * Get all parts with low stock.
     */
    public function getLowStockParts(): Collection
    {
        return Part::where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->orderBy('stock_quantity')
            ->get();
    }

    /**
     * Adjust stock quantity (for manual inventory adjustments).
     */
    public function adjustStock(Part $part, int $adjustment, string $reason = ''): Part
    {
        $newQuantity = $part->stock_quantity + $adjustment;

        if ($newQuantity < 0) {
            throw ValidationException::withMessages([
                'adjustment' => 'Stock cannot go below zero.',
            ]);
        }

        $part->update(['stock_quantity' => $newQuantity]);

        return $part->fresh();
    }
}
