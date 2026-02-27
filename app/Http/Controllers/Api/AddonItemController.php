<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\AddonItem;
use Illuminate\Http\Request;

class AddonItemController extends Controller
{
    public function store(Request $request, Addon $addon)
    {
        $orgId = $request->user()->current_organisation_id;
        abort_unless($addon->organisation_id === $orgId, 404);

        $data = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'quantity_per_unit' => ['required', 'integer', 'min:1'],
        ]);

        // Upsert to prevent duplicates
        $item = AddonItem::updateOrCreate(
            ['addon_id' => $addon->id, 'inventory_item_id' => $data['inventory_item_id']],
            ['quantity_per_unit' => $data['quantity_per_unit']]
        );

        return response()->json(['data' => $item->load('inventoryItem')], 201);
    }

    public function destroy(Request $request, Addon $addon, AddonItem $item)
    {
        $orgId = $request->user()->current_organisation_id;
        abort_unless($addon->organisation_id === $orgId, 404);
        abort_unless($item->addon_id === $addon->id, 404);

        $item->delete();

        return response()->json(['ok' => true]);
    }
}