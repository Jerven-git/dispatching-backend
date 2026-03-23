<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@dispatch.test',
            'password' => 'password',
            'role' => 'admin',
            'phone' => '0400000001',
        ]);

        // Create dispatcher
        User::create([
            'name' => 'Dispatcher User',
            'email' => 'dispatcher@dispatch.test',
            'password' => 'password',
            'role' => 'dispatcher',
            'phone' => '0400000002',
        ]);

        // Create technicians
        $tech1 = User::create([
            'name' => 'John Technician',
            'email' => 'tech1@dispatch.test',
            'password' => 'password',
            'role' => 'technician',
            'phone' => '0400000003',
        ]);

        $tech2 = User::create([
            'name' => 'Jane Technician',
            'email' => 'tech2@dispatch.test',
            'password' => 'password',
            'role' => 'technician',
            'phone' => '0400000004',
        ]);

        // Create services
        $services = [
            ['name' => 'Aircon Cleaning', 'description' => 'Full aircon cleaning and maintenance', 'base_price' => 150.00, 'estimated_duration_minutes' => 60],
            ['name' => 'Plumbing Repair', 'description' => 'General plumbing repair service', 'base_price' => 200.00, 'estimated_duration_minutes' => 90],
            ['name' => 'Electrical Repair', 'description' => 'Electrical diagnostics and repair', 'base_price' => 180.00, 'estimated_duration_minutes' => 120],
            ['name' => 'House Cleaning', 'description' => 'Full house cleaning service', 'base_price' => 250.00, 'estimated_duration_minutes' => 180],
            ['name' => 'Appliance Repair', 'description' => 'Home appliance repair and maintenance', 'base_price' => 160.00, 'estimated_duration_minutes' => 90],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }

        // Create sample customers
        $customer1 = Customer::create([
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
            'phone' => '0412345678',
            'address' => '123 Main Street',
            'city' => 'Sydney',
            'state' => 'NSW',
            'zip_code' => '2000',
        ]);

        $customer2 = Customer::create([
            'name' => 'Bob Williams',
            'email' => 'bob@example.com',
            'phone' => '0423456789',
            'address' => '456 Oak Avenue',
            'city' => 'Melbourne',
            'state' => 'VIC',
            'zip_code' => '3000',
        ]);

        // Create sample jobs
        ServiceJob::create([
            'customer_id' => $customer1->id,
            'service_id' => 1,
            'technician_id' => $tech1->id,
            'created_by' => 1,
            'status' => 'assigned',
            'priority' => 'medium',
            'description' => 'Aircon not cooling properly',
            'address' => '123 Main Street, Sydney NSW 2000',
            'scheduled_date' => now()->addDay(),
            'scheduled_time' => '09:00',
            'total_cost' => 150.00,
        ]);

        ServiceJob::create([
            'customer_id' => $customer2->id,
            'service_id' => 2,
            'technician_id' => $tech2->id,
            'created_by' => 1,
            'status' => 'pending',
            'priority' => 'high',
            'description' => 'Leaking kitchen sink',
            'address' => '456 Oak Avenue, Melbourne VIC 3000',
            'scheduled_date' => now()->addDays(2),
            'scheduled_time' => '14:00',
            'total_cost' => 200.00,
        ]);
    }
}
