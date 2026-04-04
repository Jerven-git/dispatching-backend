<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Jobs
            ['name' => 'View Jobs', 'slug' => 'jobs.view', 'group' => 'jobs'],
            ['name' => 'Create Jobs', 'slug' => 'jobs.create', 'group' => 'jobs'],
            ['name' => 'Update Jobs', 'slug' => 'jobs.update', 'group' => 'jobs'],
            ['name' => 'Delete Jobs', 'slug' => 'jobs.delete', 'group' => 'jobs'],
            ['name' => 'Assign Jobs', 'slug' => 'jobs.assign', 'group' => 'jobs'],

            // Customers
            ['name' => 'View Customers', 'slug' => 'customers.view', 'group' => 'customers'],
            ['name' => 'Create Customers', 'slug' => 'customers.create', 'group' => 'customers'],
            ['name' => 'Update Customers', 'slug' => 'customers.update', 'group' => 'customers'],
            ['name' => 'Delete Customers', 'slug' => 'customers.delete', 'group' => 'customers'],

            // Invoices
            ['name' => 'View Invoices', 'slug' => 'invoices.view', 'group' => 'invoices'],
            ['name' => 'Create Invoices', 'slug' => 'invoices.create', 'group' => 'invoices'],
            ['name' => 'Update Invoices', 'slug' => 'invoices.update', 'group' => 'invoices'],
            ['name' => 'Delete Invoices', 'slug' => 'invoices.delete', 'group' => 'invoices'],

            // Parts
            ['name' => 'View Parts', 'slug' => 'parts.view', 'group' => 'parts'],
            ['name' => 'Create Parts', 'slug' => 'parts.create', 'group' => 'parts'],
            ['name' => 'Update Parts', 'slug' => 'parts.update', 'group' => 'parts'],
            ['name' => 'Delete Parts', 'slug' => 'parts.delete', 'group' => 'parts'],
            ['name' => 'Adjust Stock', 'slug' => 'parts.adjust_stock', 'group' => 'parts'],

            // Services
            ['name' => 'View Services', 'slug' => 'services.view', 'group' => 'services'],
            ['name' => 'Create Services', 'slug' => 'services.create', 'group' => 'services'],
            ['name' => 'Update Services', 'slug' => 'services.update', 'group' => 'services'],
            ['name' => 'Delete Services', 'slug' => 'services.delete', 'group' => 'services'],

            // Users
            ['name' => 'View Users', 'slug' => 'users.view', 'group' => 'users'],
            ['name' => 'Create Users', 'slug' => 'users.create', 'group' => 'users'],
            ['name' => 'Update Users', 'slug' => 'users.update', 'group' => 'users'],
            ['name' => 'Delete Users', 'slug' => 'users.delete', 'group' => 'users'],

            // Reports & Analytics
            ['name' => 'View Reports', 'slug' => 'reports.view', 'group' => 'reports'],
            ['name' => 'Export Reports', 'slug' => 'reports.export', 'group' => 'reports'],
            ['name' => 'View Analytics', 'slug' => 'analytics.view', 'group' => 'reports'],

            // Scheduled Reports
            ['name' => 'View Scheduled Reports', 'slug' => 'scheduled_reports.view', 'group' => 'reports'],
            ['name' => 'Create Scheduled Reports', 'slug' => 'scheduled_reports.create', 'group' => 'reports'],
            ['name' => 'Update Scheduled Reports', 'slug' => 'scheduled_reports.update', 'group' => 'reports'],
            ['name' => 'Delete Scheduled Reports', 'slug' => 'scheduled_reports.delete', 'group' => 'reports'],

            // Settings & Tenants
            ['name' => 'Manage Settings', 'slug' => 'settings.manage', 'group' => 'settings'],
            ['name' => 'Manage Roles', 'slug' => 'roles.manage', 'group' => 'settings'],
            ['name' => 'View Audit Logs', 'slug' => 'audit_logs.view', 'group' => 'settings'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['slug' => $perm['slug']],
                $perm,
            );
        }

        // Create system roles with default permissions
        $this->createSystemRole('admin', 'Administrator', 'Full system access', Permission::pluck('id')->all());

        $dispatcherPerms = Permission::whereIn('slug', [
            'jobs.view', 'jobs.create', 'jobs.update', 'jobs.assign',
            'customers.view', 'customers.create', 'customers.update',
            'invoices.view', 'invoices.create', 'invoices.update',
            'parts.view',
            'services.view',
            'users.view',
            'reports.view', 'reports.export', 'analytics.view',
        ])->pluck('id')->all();
        $this->createSystemRole('dispatcher', 'Dispatcher', 'Job management and scheduling', $dispatcherPerms);

        $techPerms = Permission::whereIn('slug', [
            'jobs.view', 'jobs.update',
            'parts.view',
        ])->pluck('id')->all();
        $this->createSystemRole('technician', 'Technician', 'Field work and job updates', $techPerms);
    }

    private function createSystemRole(string $slug, string $name, string $description, array $permissionIds): void
    {
        $role = Role::updateOrCreate(
            ['slug' => $slug, 'tenant_id' => null],
            [
                'name' => $name,
                'description' => $description,
                'is_system' => true,
            ],
        );

        $role->permissions()->sync($permissionIds);
    }
}
