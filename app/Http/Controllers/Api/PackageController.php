<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Package;
use App\Support\OrgRole;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryReservation;
use App\Models\InventoryStock;


class PackageController extends Controller
{
   public function index(Request $request)
{
    $org = $request->attributes->get('currentOrganisation');

    $packages = \App\Models\Package::query()
        ->where('organisation_id', $org->id)
        ->with(['packageItems.inventoryItem', 'packageItems'])
        ->orderBy('name')
        ->get();

    return response()->json([
        'data' => $packages,
    ]);
}

public function store(Request $request)
{
    $org = $request->attributes->get('currentOrganisation');
    $role = $request->attributes->get('currentOrgRole');

    // Only admin/owner allowed
    if (!\App\Support\OrgRole::isAdminLike($role)) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $validated = $request->validate([
        'name' => [
            'required',
            'string',
            'max:255',
            \Illuminate\Validation\Rule::unique('packages')
                ->where(fn ($q) => $q->where('organisation_id', $org->id)),
        ],
        'description' => ['nullable', 'string'],
        'is_active' => ['boolean'],
    ]);

    $package = \App\Models\Package::create([
        'organisation_id' => $org->id,
        'name' => $validated['name'],
        'description' => $validated['description'] ?? null,
        'is_active' => $validated['is_active'] ?? true,
    ]);

    return response()->json([
        'data' => $package,
    ], 201);
}

public function update(Request $request, int $id)
{
    $org = $request->attributes->get('currentOrganisation');
    $role = $request->attributes->get('currentOrgRole');

    if (!\App\Support\OrgRole::isAdminLike($role)) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $package = \App\Models\Package::where('organisation_id', $org->id)->findOrFail($id);

    $validated = $request->validate([
        'name' => [
            'sometimes',
            'required',
            'string',
            'max:255',
            Rule::unique('packages')->where(fn ($q) =>
                $q->where('organisation_id', $org->id)
            )->ignore($package->id),
        ],
        'description' => ['sometimes', 'nullable', 'string'],
        'is_active' => ['sometimes', 'boolean'],
    ]);

    $package->fill($validated);
    $package->save();

    return response()->json(['data' => $package]);
}


public function show(Request $request, int $id)
{
    $org = $request->attributes->get('currentOrganisation');

    $package = \App\Models\Package::where('organisation_id', $org->id)
        ->with(['packageItems.inventoryItem'])
        ->findOrFail($id);

    return response()->json(['data' => $package]);
}
public function updateItems(Request $request, int $id)
{
    $org = $request->attributes->get('currentOrganisation');
    $role = $request->attributes->get('currentOrgRole');

    if (!\App\Support\OrgRole::isAdminLike($role)) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $package = \App\Models\Package::where('organisation_id', $org->id)->findOrFail($id);

    $validated = $request->validate([
        'items' => ['required', 'array', 'min:1'],
        'items.*.inventory_item_id' => [
            'required',
            'integer',
            // must exist AND belong to current org
            Rule::exists('inventory_items', 'id')->where(fn ($q) =>
                $q->where('organisation_id', $org->id)
            ),
        ],
        'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
    ]);

    // Ensure no duplicate inventory_item_id entries in the payload
    $ids = collect($validated['items'])->pluck('inventory_item_id');
    if ($ids->count() !== $ids->unique()->count()) {
        return response()->json(['message' => 'Duplicate inventory_item_id in items payload.'], 422);
    }

    DB::transaction(function () use ($package, $validated) {
        // Replace-all strategy
        $package->packageItems()->delete();

    $rows = collect($validated['items'])->map(fn ($item) => [
    'package_id' => $package->id,
    'inventory_item_id' => $item['inventory_item_id'],
    'quantity' => $item['quantity'],
    'created_at' => now(),
    'updated_at' => now(),
])->all();


        $package->packageItems()->insert($rows);
    });

    // Return refreshed package with items
    $package->load(['packageItems.inventoryItem']);

    return response()->json(['data' => $package]);
}

public function checkAvailability(Request $request)
{
    $org = $request->attributes->get('currentOrganisation');

    $validated = $request->validate([
        'package_id' => [
            'required',
            'integer',
            Rule::exists('packages', 'id')->where(fn ($q) =>
                $q->where('organisation_id', $org->id)
            ),
        ],
        'start_at' => ['required', 'date'],
        'end_at' => ['required', 'date', 'after:start_at'],
    ]);

    $startAt = $validated['start_at'];
    $endAt = $validated['end_at'];

    $package = Package::where('organisation_id', $org->id)
        ->with(['packageItems'])
        ->findOrFail($validated['package_id']);

    if ($package->packageItems->isEmpty()) {
        return response()->json([
            'available' => false,
            'message' => 'Package has no items.',
            'missing_items' => [],
        ], 422);
    }

    $missing = [];
    $breakdown = [];

    foreach ($package->packageItems as $pi) {
        $itemId = $pi->inventory_item_id;
        $required = (int) $pi->quantity;

        $stock = InventoryStock::where('organisation_id', $org->id)
            ->where('inventory_item_id', $itemId)
            ->first();

        $total = (int) ($stock?->total_quantity ?? 0);

        $reserved = (int) InventoryReservation::where('organisation_id', $org->id)
            ->where('inventory_item_id', $itemId)
            ->where('status', 'active')
            ->where('start_at', '<', $endAt)   // overlap rule
            ->where('end_at', '>', $startAt)   // overlap rule
            ->sum('reserved_quantity');

        $availableQty = max(0, $total - $reserved);

        $breakdown[] = [
            'inventory_item_id' => $itemId,
            'required' => $required,
            'total' => $total,
            'reserved' => $reserved,
            'available' => $availableQty,
        ];

        if ($availableQty < $required) {
            $missing[] = [
                'inventory_item_id' => $itemId,
                'required' => $required,
                'available' => $availableQty,
            ];
        }
    }

    return response()->json([
        'available' => count($missing) === 0,
        'missing_items' => $missing,
        'breakdown' => $breakdown,
    ]);
}

}
