<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->role, fn ($q, $role) => $q->where('role', $role))
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
            )
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return response()->json(UserResource::collection($users)->response()->getData(true));
    }

    public function technicians(): JsonResponse
    {
        $technicians = User::where('role', 'technician')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'technicians' => UserResource::collection($technicians),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($user),
        ]);
    }
}
