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
        $org = $request->attributes->get('currentOrganisation');

        $addons = Addon::where('organisation_id', $org->id)
            ->where('is_active', true)
            ->with(['items.inventory_item'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $addons
        ]);
    }

    public function store(Request $request)
    {
        $org = $request->attributes->get('currentOrganisation');
        $role = $request->attributes->get('currentOrgRole');

        // same auth pattern as PackageController
        if (!\App\Support\OrgRole::isAdminLike($role)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'pricing_type' => ['required', Rule::in(['fixed', 'per_unit'])],
            'price_cents' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'integer'],
            'items.*.quantity_per_unit' => ['required', 'integer', 'min:1'],
        ]);

        $items = $data['items'];
        unset($data['items']);

        $data['is_active'] = $data['is_active'] ?? true;

        $addon = Addon::create($data + [
            'organisation_id' => $org->id
        ]);

        $addon->items()->createMany($items);

        return response()->json([
            'data' => $addon->load(['items.inventory_item'])
        ], 201);
    }

    public function show(Request $request, Addon $addon)
    {
        $org = $request->attributes->get('currentOrganisation');

        abort_unless($addon->organisation_id === $org->id, 404);

        return response()->json([
            'data' => $addon->load(['items.inventory_item'])
        ]);
    }

    public function update(Request $request, Addon $addon)
    {
        $org = $request->attributes->get('currentOrganisation');
        $role = $request->attributes->get('currentOrgRole');

        if (!\App\Support\OrgRole::isAdminLike($role)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        abort_unless($addon->organisation_id === $org->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'pricing_type' => ['sometimes', 'required', Rule::in(['fixed', 'per_unit'])],
            'price_cents' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],

            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required_with:items', 'integer'],
            'items.*.quantity_per_unit' => ['required_with:items', 'integer', 'min:1'],
        ]);

        $items = $data['items'] ?? null;
        unset($data['items']);

        $addon->update($data);

        if (is_array($items)) {
            $addon->items()->delete();
            $addon->items()->createMany($items);
        }

        return response()->json([
            'data' => $addon->fresh()->load(['items.inventory_item'])
        ]);
    }

    public function destroy(Request $request, Addon $addon)
    {
        $org = $request->attributes->get('currentOrganisation');
        $role = $request->attributes->get('currentOrgRole');

        if (!\App\Support\OrgRole::isAdminLike($role)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        abort_unless($addon->organisation_id === $org->id, 404);

        $addon->update([
            'is_active' => false
        ]);

        return response()->json([
            'data' => $addon->fresh()->load(['items.inventory_item'])
        ]);
    }
}