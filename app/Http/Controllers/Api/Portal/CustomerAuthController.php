<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CustomerAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $customer = Customer::where('email', $request->email)->first();

        if (! $customer || ! $customer->portal_access || ! Hash::check($request->password, $customer->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        Auth::guard('customer')->login($customer);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'customer' => new CustomerResource($customer),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'customer' => new CustomerResource($request->user('customer')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('customer')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
