<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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


}
