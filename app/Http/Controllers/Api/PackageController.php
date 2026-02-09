<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Package;
use App\Support\OrgRole;

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

}
