<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Role::with('permissions:id,name,slug,group');

        // Scope to current user's tenant if they have one
        if ($request->user()->tenant_id) {
            $query->where(function ($q) use ($request) {
                $q->where('tenant_id', $request->user()->tenant_id)
                    ->orWhere('is_system', true);
            });
        }

        $roles = $query->orderBy('name')->get();

        return response()->json(['data' => $roles]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $tenantId = $request->user()->tenant_id;

        // Check slug uniqueness within tenant
        $exists = Role::where('tenant_id', $tenantId)
            ->where('slug', $data['slug'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'A role with this slug already exists.',
            ], 422);
        }

        $role = Role::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        $role->permissions()->sync($data['permissions']);
        $role->load('permissions:id,name,slug,group');

        return response()->json([
            'message' => 'Role created successfully.',
            'role' => $role,
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load(['permissions:id,name,slug,group', 'users:id,name,email']);

        return response()->json(['role' => $role]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json([
                'message' => 'System roles cannot be modified.',
            ], 403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['sometimes', 'array', 'min:1'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->update(array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
        ]));

        if (isset($data['permissions'])) {
            $role->permissions()->sync($data['permissions']);
        }

        $role->load('permissions:id,name,slug,group');

        return response()->json([
            'message' => 'Role updated successfully.',
            'role' => $role,
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json([
                'message' => 'System roles cannot be deleted.',
            ], 403);
        }

        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete a role that is assigned to users.',
            ], 422);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::orderBy('group')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $permissions]);
    }
}
