<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartResource;
use App\Models\Part;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Part::query();

        $query->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%")
            ->orWhere('sku', 'like', "%{$search}%")
        )
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        $parts = $query->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return response()->json(PartResource::collection($parts)->response()->getData(true));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sku' => ['required', 'string', 'max:100', 'unique:parts,sku'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $part = Part::create($data);

        return response()->json([
            'message' => 'Part created successfully.',
            'part' => new PartResource($part),
        ], 201);
    }

    public function show(Part $part): JsonResponse
    {
        return response()->json([
            'part' => new PartResource($part),
        ]);
    }

    public function update(Request $request, Part $part): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sku' => ['sometimes', 'string', 'max:100', "unique:parts,sku,{$part->id}"],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $part->update($data);

        return response()->json([
            'message' => 'Part updated successfully.',
            'part' => new PartResource($part),
        ]);
    }

    public function destroy(Part $part): JsonResponse
    {
        if ($part->jobParts()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a part that has been used on jobs. Deactivate it instead.',
            ], 422);
        }

        $part->delete();

        return response()->json([
            'message' => 'Part deleted successfully.',
        ]);
    }

    public function lowStock(): JsonResponse
    {
        $parts = $this->inventoryService->getLowStockParts();

        return response()->json([
            'parts' => PartResource::collection($parts),
        ]);
    }

    public function adjustStock(Request $request, Part $part): JsonResponse
    {
        $data = $request->validate([
            'adjustment' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $part = $this->inventoryService->adjustStock(
            $part,
            $data['adjustment'],
            $data['reason'] ?? '',
        );

        return response()->json([
            'message' => 'Stock adjusted successfully.',
            'part' => new PartResource($part),
        ]);
    }
}
