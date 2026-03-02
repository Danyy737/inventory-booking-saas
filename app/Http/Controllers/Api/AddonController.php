<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AddonController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->current_organisation_id;

        // Hide "deleted" (inactive) addons by default
        $addons = Addon::where('organisation_id', $orgId)
            ->where('is_active', true)
            ->with(['items.inventory_item'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $addons]);
    }

    public function store(Request $request)
    {
        $orgId = $request->user()->current_organisation_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'pricing_type' => ['required', Rule::in(['fixed', 'per_unit'])],
            'price_cents' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],

            // Addon items (required on create)
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'integer'],
            'items.*.quantity_per_unit' => ['required', 'integer', 'min:1'],
        ]);

        $items = $data['items'];
        unset($data['items']);

        // Default active to true if not provided
        $data['is_active'] = $data['is_active'] ?? true;

        $addon = Addon::create($data + ['organisation_id' => $orgId]);

        // Persist addon items
        $addon->items()->createMany($items);

        return response()->json(['data' => $addon->load(['items.inventory_item'])], 201);
    }

    public function show(Request $request, Addon $addon)
    {
        $this->assertTenant($request, $addon);

        return response()->json(['data' => $addon->load(['items.inventory_item'])]);
    }

    public function update(Request $request, Addon $addon)
    {
        $this->assertTenant($request, $addon);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'pricing_type' => ['sometimes', 'required', Rule::in(['fixed', 'per_unit'])],
            'price_cents' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],

            // Items optional on update; if provided, we replace them
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required_with:items', 'integer'],
            'items.*.quantity_per_unit' => ['required_with:items', 'integer', 'min:1'],
        ]);

        $items = $data['items'] ?? null;
        unset($data['items']);

        $addon->update($data);

        // If items provided, replace existing rows
        if (is_array($items)) {
            $addon->items()->delete();
            $addon->items()->createMany($items);
        }

        return response()->json(['data' => $addon->fresh()->load(['items.inventory_item'])]);
    }

    // Soft "delete" by deactivating (keeps history + avoids breaking old bookings)
    public function destroy(Request $request, Addon $addon)
    {
        $this->assertTenant($request, $addon);

        $addon->update(['is_active' => false]);

        return response()->json(['data' => $addon->fresh()->load(['items.inventory_item'])]);
    }

    private function assertTenant(Request $request, Addon $addon): void
    {
        $orgId = $request->user()->current_organisation_id;
        abort_unless($addon->organisation_id === $orgId, 404);
    }
}