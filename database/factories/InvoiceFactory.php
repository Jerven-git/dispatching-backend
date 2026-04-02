<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 1000);
        $taxRate = fake()->randomElement([0, 5, 8, 10]);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);

        return [
            'service_job_id' => ServiceJob::factory()->completed(),
            'customer_id' => Customer::factory(),
            'created_by' => User::factory()->state(['role' => 'admin']),
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
            'status' => 'draft',
            'issued_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
