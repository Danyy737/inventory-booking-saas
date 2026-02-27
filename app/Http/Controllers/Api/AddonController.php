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

        $addons = Addon::where('organisation_id', $orgId)
            ->with(['items.inventoryItem'])
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
        ]);

        $addon = Addon::create($data + ['organisation_id' => $orgId]);

        return response()->json(['data' => $addon->load(['items.inventoryItem'])], 201);
    }

    public function show(Request $request, Addon $addon)
    {
        $this->assertTenant($request, $addon);

        return response()->json(['data' => $addon->load(['items.inventoryItem'])]);
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
        ]);

        $addon->update($data);

        return response()->json(['data' => $addon->fresh()->load(['items.inventoryItem'])]);
    }

    // Don't hard delete (keeps history + avoids breaking old bookings)
    public function destroy(Request $request, Addon $addon)
    {
        $this->assertTenant($request, $addon);

        $addon->update(['is_active' => false]);

        return response()->json(['data' => $addon->fresh()]);
    }

    private function assertTenant(Request $request, Addon $addon): void
    {
        $orgId = $request->user()->current_organisation_id;
        abort_unless($addon->organisation_id === $orgId, 404);
    }
}