<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Support\OrgRole;
use Illuminate\Database\QueryException;

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function ensureAdminLike($user)
    {
        if (!OrgRole::isAdminLike(OrgRole::currentRole($user))) {
            abort(response()->json(['message' => 'Forbidden.'], 403));
        }
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->current_organisation_id) {
            return response()->json(['message' => 'No active organisation selected.'], 409);
        }

        $items = InventoryItem::query()
            ->where('organisation_id', $user->current_organisation_id)
            ->with(['stock:id,organisation_id,inventory_item_id,total_quantity'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->ensureAdminLike($user);

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
            'total_quantity' => ['required', 'integer', 'min:0'],
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

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $this->ensureAdminLike($user);

        $item = InventoryItem::where('organisation_id', $user->current_organisation_id)
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
            'total_quantity' => ['sometimes', 'integer', 'min:0'],
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

        return response()->json(['data' => $item->load('stock')]);
    }

public function destroy(Request $request, $id)
{
    $user = $request->user();
    $this->ensureAdminLike($user);

    $item = InventoryItem::where('organisation_id', $user->current_organisation_id)
        ->where('id', $id)
        ->firstOrFail();

    try {
        $item->delete();
        return response()->noContent();
    } catch (QueryException $e) {
        // FK restrict (e.g. package_items references this item)
        return response()->json([
            'message' => 'Cannot delete this item because it is used in one or more packages. Remove it from those packages first.'
        ], 409);
    }
}


    public function checkAvailability(Request $request)
    {
        $user = $request->user();

        if (!$user->current_organisation_id) {
            return response()->json(['message' => 'No active organisation selected.'], 409);
        }

        $orgId = (int) $user->current_organisation_id;

        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $item = InventoryItem::where('organisation_id', $orgId)
            ->where('id', $validated['inventory_item_id'])
            ->with('stock')
            ->firstOrFail();

        $total = (int) optional($item->stock)->total_quantity;

        $reserved = (int) $item->reservations()
            ->where('organisation_id', $orgId)
            ->where('status', 'active')
            ->where('start_at', '<', $validated['end_at'])
            ->where('end_at', '>', $validated['start_at'])
            ->sum('reserved_quantity');

        $remaining = max(0, $total - $reserved);

        return response()->json([
            'data' => [
                'available' => $remaining >= $validated['quantity'],
                'remaining_quantity' => $remaining,
            ],
        ]);
    }
}
