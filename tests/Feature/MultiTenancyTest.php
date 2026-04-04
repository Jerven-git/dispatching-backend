<?php

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    // Seed permissions for RBAC tests
    $this->seed(PermissionSeeder::class);

    $this->tenant1 = Tenant::factory()->create(['name' => 'Company A', 'slug' => 'company-a']);
    $this->tenant2 = Tenant::factory()->create(['name' => 'Company B', 'slug' => 'company-b']);

    $this->admin = User::factory()->create([
        'role' => 'admin',
        'tenant_id' => $this->tenant1->id,
    ]);

    $this->dispatcher = User::factory()->create([
        'role' => 'dispatcher',
        'tenant_id' => $this->tenant1->id,
    ]);

    $this->technician = User::factory()->create([
        'role' => 'technician',
        'tenant_id' => $this->tenant1->id,
    ]);

    $this->otherAdmin = User::factory()->create([
        'role' => 'admin',
        'tenant_id' => $this->tenant2->id,
    ]);
});

// ── Tenant CRUD ────────────────────────────────────────────────

describe('Tenant Management', function () {
    it('admin can create a tenant', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/tenants', [
                'name' => 'New Company',
                'slug' => 'new-company',
                'plan' => 'pro',
                'max_users' => 25,
            ]);

        $response->assertCreated();
        expect($response->json('tenant.name'))->toBe('New Company');
        expect($response->json('tenant.plan'))->toBe('pro');
    });

    it('rejects duplicate slug', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/tenants', [
                'name' => 'Duplicate',
                'slug' => 'company-a', // already exists
            ]);

        $response->assertUnprocessable();
    });

    it('admin can list tenants', function () {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/tenants');

        $response->assertOk();
        expect($response->json('tenants'))->toHaveCount(2);
    });

    it('admin can update a tenant', function () {
        $response = $this->actingAs($this->admin)
            ->putJson("/api/tenants/{$this->tenant1->id}", [
                'name' => 'Updated Company A',
                'plan' => 'enterprise',
            ]);

        $response->assertOk();
        expect($this->tenant1->fresh()->name)->toBe('Updated Company A');
        expect($this->tenant1->fresh()->plan)->toBe('enterprise');
    });

    it('cannot delete tenant with users', function () {
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/tenants/{$this->tenant1->id}");

        $response->assertUnprocessable();
    });

    it('can delete empty tenant', function () {
        $empty = Tenant::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/tenants/{$empty->id}");

        $response->assertOk();
        expect(Tenant::find($empty->id))->toBeNull();
    });

    it('tenant tracks user count', function () {
        expect($this->tenant1->getUserCount())->toBe(3); // admin, dispatcher, technician
        expect($this->tenant1->canAddUsers())->toBeTrue();
    });

    it('technician cannot manage tenants', function () {
        $this->actingAs($this->technician)
            ->getJson('/api/tenants')
            ->assertForbidden();
    });
});

// ── Roles & Permissions ────────────────────────────────────────

describe('Roles & Permissions', function () {
    it('seeder creates system roles and permissions', function () {
        expect(Permission::count())->toBeGreaterThan(30);
        expect(Role::where('is_system', true)->count())->toBe(3); // admin, dispatcher, technician
    });

    it('admin can list permissions grouped', function () {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/permissions');

        $response->assertOk();
        $groups = $response->json('permissions');
        expect($groups)->toHaveKey('jobs');
        expect($groups)->toHaveKey('customers');
        expect($groups)->toHaveKey('settings');
    });

    it('admin can create a custom role', function () {
        $perms = Permission::whereIn('slug', ['jobs.view', 'jobs.create', 'customers.view'])
            ->pluck('id')
            ->all();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/roles', [
                'name' => 'Junior Dispatcher',
                'slug' => 'junior-dispatcher',
                'description' => 'Limited dispatcher access',
                'permissions' => $perms,
            ]);

        $response->assertCreated();
        expect($response->json('role.name'))->toBe('Junior Dispatcher');
        expect($response->json('role.permissions'))->toHaveCount(3);
        expect($response->json('role.is_system'))->toBeFalse();
    });

    it('admin can list roles', function () {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/roles');

        $response->assertOk();
        // System roles + any custom roles
        expect(count($response->json('roles')))->toBeGreaterThanOrEqual(3);
    });

    it('admin can update a custom role permissions', function () {
        $role = Role::create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Custom',
            'slug' => 'custom',
            'is_system' => false,
        ]);

        $newPerms = Permission::whereIn('slug', ['jobs.view', 'parts.view'])->pluck('id')->all();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/roles/{$role->id}", [
                'name' => 'Updated Custom',
                'permissions' => $newPerms,
            ]);

        $response->assertOk();
        expect($role->fresh()->name)->toBe('Updated Custom');
        expect($role->fresh()->permissions)->toHaveCount(2);
    });

    it('cannot modify system roles', function () {
        $systemRole = Role::where('is_system', true)->first();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/roles/{$systemRole->id}", [
                'name' => 'Hacked',
            ]);

        $response->assertForbidden();
    });

    it('cannot delete system roles', function () {
        $systemRole = Role::where('is_system', true)->first();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/roles/{$systemRole->id}");

        $response->assertForbidden();
    });

    it('cannot delete role with assigned users', function () {
        $role = Role::create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'In Use',
            'slug' => 'in-use',
            'is_system' => false,
        ]);

        $this->dispatcher->update(['custom_role_id' => $role->id]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/roles/{$role->id}");

        $response->assertUnprocessable();
    });

    it('can delete unused custom role', function () {
        $role = Role::create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Unused',
            'slug' => 'unused',
            'is_system' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/roles/{$role->id}");

        $response->assertOk();
    });
});

// ── Permission Checking ────────────────────────────────────────

describe('Permission Checking', function () {
    it('admin has all permissions', function () {
        expect($this->admin->hasPermission('jobs.view'))->toBeTrue();
        expect($this->admin->hasPermission('users.delete'))->toBeTrue();
        expect($this->admin->hasPermission('anything.at.all'))->toBeTrue();
    });

    it('dispatcher has default dispatcher permissions', function () {
        expect($this->dispatcher->hasPermission('jobs.view'))->toBeTrue();
        expect($this->dispatcher->hasPermission('jobs.create'))->toBeTrue();
        expect($this->dispatcher->hasPermission('users.delete'))->toBeFalse();
    });

    it('technician has limited permissions', function () {
        expect($this->technician->hasPermission('jobs.view'))->toBeTrue();
        expect($this->technician->hasPermission('jobs.update'))->toBeTrue();
        expect($this->technician->hasPermission('jobs.create'))->toBeFalse();
        expect($this->technician->hasPermission('users.view'))->toBeFalse();
    });

    it('custom role overrides default permissions', function () {
        $role = Role::create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Super Tech',
            'slug' => 'super-tech',
            'is_system' => false,
        ]);

        $permIds = Permission::whereIn('slug', ['jobs.view', 'jobs.create', 'jobs.update', 'parts.view', 'parts.create'])
            ->pluck('id')->all();
        $role->permissions()->sync($permIds);

        $this->technician->update(['custom_role_id' => $role->id]);

        // Now has extra permissions via custom role
        expect($this->technician->fresh()->hasPermission('jobs.create'))->toBeTrue();
        expect($this->technician->fresh()->hasPermission('parts.create'))->toBeTrue();
        // But not permissions not in custom role
        expect($this->technician->fresh()->hasPermission('users.view'))->toBeFalse();
    });
});

// ── Audit Logs ─────────────────────────────────────────────────

describe('Audit Logs', function () {
    it('admin can view audit logs', function () {
        AuditLog::create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->admin->id,
            'action' => 'created',
            'auditable_type' => 'App\\Models\\ServiceJob',
            'auditable_id' => 1,
        ]);

        AuditLog::create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->admin->id,
            'action' => 'updated',
            'auditable_type' => 'App\\Models\\ServiceJob',
            'auditable_id' => 1,
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'assigned'],
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/audit-logs');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    });

    it('can filter audit logs by action', function () {
        AuditLog::create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->admin->id,
            'action' => 'created',
        ]);
        AuditLog::create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->admin->id,
            'action' => 'deleted',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/audit-logs?action=created');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can filter audit logs by user', function () {
        AuditLog::create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->admin->id,
            'action' => 'created',
        ]);
        AuditLog::create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->dispatcher->id,
            'action' => 'created',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/audit-logs?user_id={$this->admin->id}");

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('can view a single audit log entry', function () {
        $log = AuditLog::create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->admin->id,
            'action' => 'updated',
            'auditable_type' => 'App\\Models\\ServiceJob',
            'auditable_id' => 1,
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'assigned'],
            'ip_address' => '192.168.1.1',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/audit-logs/{$log->id}");

        $response->assertOk();
        expect($response->json('audit_log.action'))->toBe('updated');
        expect($response->json('audit_log.old_values.status'))->toBe('pending');
        expect($response->json('audit_log.new_values.status'))->toBe('assigned');
    });

    it('scopes audit logs to tenant', function () {
        AuditLog::create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->admin->id,
            'action' => 'created',
        ]);
        AuditLog::create([
            'tenant_id' => $this->tenant2->id,
            'user_id' => $this->otherAdmin->id,
            'action' => 'created',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/audit-logs');

        $response->assertOk();
        // Should only see tenant1's logs
        expect($response->json('data'))->toHaveCount(1);
    });

    it('technician cannot view audit logs', function () {
        $this->actingAs($this->technician)
            ->getJson('/api/audit-logs')
            ->assertForbidden();
    });
});

// ── Auditable Trait ────────────────────────────────────────────

describe('Auditable Trait', function () {
    it('logs when a tenant is created', function () {
        $this->actingAs($this->admin);

        $tenant = Tenant::create([
            'name' => 'Audited Company',
            'slug' => 'audited-company',
        ]);

        $log = AuditLog::where('auditable_type', 'App\\Models\\Tenant')
            ->where('auditable_id', $tenant->id)
            ->where('action', 'created')
            ->first();

        expect($log)->not->toBeNull();
        expect($log->new_values['name'])->toBe('Audited Company');
    });

    it('logs when a tenant is updated', function () {
        $this->actingAs($this->admin);

        $this->tenant1->update(['name' => 'Renamed Company']);

        $log = AuditLog::where('auditable_type', 'App\\Models\\Tenant')
            ->where('auditable_id', $this->tenant1->id)
            ->where('action', 'updated')
            ->first();

        expect($log)->not->toBeNull();
        expect($log->old_values['name'])->toBe('Company A');
        expect($log->new_values['name'])->toBe('Renamed Company');
    });
});

// ── Tenant Model ───────────────────────────────────────────────

describe('Tenant Model', function () {
    it('checks user capacity', function () {
        $small = Tenant::factory()->create(['max_users' => 1]);
        User::factory()->create(['tenant_id' => $small->id]);

        expect($small->canAddUsers())->toBeFalse();
    });

    it('retrieves settings', function () {
        $tenant = Tenant::factory()->create([
            'settings' => ['theme' => 'dark', 'timezone' => 'America/New_York'],
        ]);

        expect($tenant->getSetting('theme'))->toBe('dark');
        expect($tenant->getSetting('timezone'))->toBe('America/New_York');
        expect($tenant->getSetting('missing', 'default'))->toBe('default');
    });
});

// ── Rate Limiting ──────────────────────────────────────────────

describe('Tenant Rate Limiting', function () {
    it('includes rate limit headers in response', function () {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard');

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    });
});
