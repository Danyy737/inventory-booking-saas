<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Same boundary rules as /api/me
        if (!$user->current_organisation_id) {
            return response()->json([
                'message' => 'No active organisation selected.',
            ], 409);
        }

        // List only items belonging to the user’s current organisation
        $items = InventoryItem::query()
            ->where('organisation_id', $user->current_organisation_id)
            ->with(['stock:id,organisation_id,inventory_item_id,total_quantity'])
            ->orderBy('name')
            ->get([
                'id',
                'organisation_id',
                'name',
                'sku',
                'description',
                'is_active',
                'created_at',
                'updated_at',
            ]);

        return response()->json([
            'data' => $items,
        ]);
    }

   public function store(Request $request)
{
    $user = $request->user();

    if (!$user->current_organisation_id) {
        return response()->json([
            'message' => 'No active organisation selected.',
        ], 409);
    }

    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'sku' => ['nullable', 'string', 'max:100'],
        'description' => ['nullable', 'string'],
        'is_active' => ['sometimes', 'boolean'],
        'total_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
    ]);

    $orgId = (int) $user->current_organisation_id;

    $item = \DB::transaction(function () use ($validated, $orgId) {
        $item = \App\Models\InventoryItem::create([
            'organisation_id' => $orgId,
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $item->stock()->create([
            'organisation_id' => $orgId,
            'total_quantity' => $validated['total_quantity'],
        ]);

        return $item->load('stock');
    });

    return response()->json([
        'data' => $item,
    ], 201);
}

}
