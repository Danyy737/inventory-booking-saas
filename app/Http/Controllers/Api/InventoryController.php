<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->current_organisation_id) {
            return response()->json([
                'message' => 'No active organisation selected.',
            ], 409);
        }

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

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->current_organisation_id) {
            return response()->json([
                'message' => 'No active organisation selected.',
            ], 409);
        }

        $orgId = (int) $user->current_organisation_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('inventory_items', 'sku')
                    ->where(fn ($q) => $q->where('organisation_id', $orgId)),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'total_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $item = DB::transaction(function () use ($validated, $orgId) {
            $item = InventoryItem::create([
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

    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->current_organisation_id) {
            return response()->json([
                'message' => 'No active organisation selected.',
            ], 409);
        }

        $item = InventoryItem::query()
            ->where('organisation_id', $user->current_organisation_id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('inventory_items', 'sku')
                    ->where(fn ($q) => $q->where('organisation_id', $user->current_organisation_id))
                    ->ignore($item->id),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'total_quantity' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ]);

        DB::transaction(function () use ($item, $validated, $user) {
            $item->update(collect($validated)->except('total_quantity')->toArray());

            if (array_key_exists('total_quantity', $validated)) {
                $item->stock()->updateOrCreate(
                    [
                        'organisation_id' => $user->current_organisation_id,
                        'inventory_item_id' => $item->id,
                    ],
                    [
                        'total_quantity' => $validated['total_quantity'],
                    ]
                );
            }
        });

        return response()->json([
            'data' => $item->load('stock'),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->current_organisation_id) {
            return response()->json([
                'message' => 'No active organisation selected.',
            ], 409);
        }

        $item = InventoryItem::query()
            ->where('organisation_id', $user->current_organisation_id)
            ->where('id', $id)
            ->firstOrFail();

        $item->delete();

        return response()->noContent();
    }

    public function checkAvailability(Request $request)
{
    $user = $request->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    if (!$user->current_organisation_id) {
        return response()->json(['message' => 'No active organisation selected.'], 409);
    }

    $orgId = (int) $user->current_organisation_id;

    $validated = $request->validate([
        'inventory_item_id' => ['required', 'integer'],
        'start_at' => ['required', 'date'],
        'end_at' => ['required', 'date', 'after:start_at'],
        'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
    ]);

    $item = InventoryItem::query()
        ->where('organisation_id', $orgId)
        ->where('id', $validated['inventory_item_id'])
        ->with('stock')
        ->firstOrFail();

    $total = (int) optional($item->stock)->total_quantity;

    $reserved = (int) $item->reservations()
        ->where('organisation_id', $orgId)
        ->where('status', 'active')
        ->where('start_at', '<', $validated['end_at'])   // overlap rule
        ->where('end_at', '>', $validated['start_at'])   // overlap rule
        ->sum('reserved_quantity');

    $remaining = max(0, $total - $reserved);
    $requested = (int) $validated['quantity'];

    return response()->json([
        'data' => [
            'inventory_item_id' => $item->id,
            'total_quantity' => $total,
            'reserved_quantity' => $reserved,
            'remaining_quantity' => $remaining,
            'requested_quantity' => $requested,
            'available' => $remaining >= $requested,
        ],
    ]);
}

}
