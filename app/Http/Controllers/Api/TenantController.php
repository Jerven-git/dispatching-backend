<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index(): JsonResponse
    {
        $tenants = Tenant::withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json(['tenants' => $tenants]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'unique:tenants,slug', 'alpha_dash'],
            'domain' => ['nullable', 'string', 'max:255', 'unique:tenants,domain'],
            'plan' => ['nullable', 'in:free,basic,pro,enterprise'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'settings' => ['nullable', 'array'],
        ]);

        $tenant = Tenant::create($data);

        return response()->json([
            'message' => 'Tenant created successfully.',
            'tenant' => $tenant,
        ], 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->loadCount('users');
        $tenant->load('roles');

        return response()->json(['tenant' => $tenant]);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:100', "unique:tenants,slug,{$tenant->id}", 'alpha_dash'],
            'domain' => ['nullable', 'string', 'max:255', "unique:tenants,domain,{$tenant->id}"],
            'plan' => ['sometimes', 'in:free,basic,pro,enterprise'],
            'max_users' => ['sometimes', 'integer', 'min:1'],
            'settings' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $tenant->update($data);

        return response()->json([
            'message' => 'Tenant updated successfully.',
            'tenant' => $tenant,
        ]);
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        if ($tenant->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete a tenant with existing users. Remove all users first.',
            ], 422);
        }

        $tenant->delete();

        return response()->json([
            'message' => 'Tenant deleted successfully.',
        ]);
    }
}
