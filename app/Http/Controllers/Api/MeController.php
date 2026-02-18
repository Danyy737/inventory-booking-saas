<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Auth-only identity endpoint.
     * Works even if no organisation is selected.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        $currentOrg = null;
        $role = null;

        if ($user->current_organisation_id) {
            $organisation = $user->organisations()
                ->whereKey($user->current_organisation_id)
                ->first();

            if ($organisation) {
                $currentOrg = [
                    'id' => $organisation->id,
                    'name' => $organisation->name,
                    'slug' => $organisation->slug,
                ];
                $role = $organisation->pivot->role ?? null;
            }
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'current_organisation_id' => $user->current_organisation_id,
            ],
            'current_organisation' => $currentOrg,
            'role' => $role,
        ]);
    }

    /**
     * Auth-only endpoint to set the active organisation.
     */
    public function selectOrganisation(Request $request)
    {
        $validated = $request->validate([
            'organisation_id' => ['required', 'integer', 'exists:organisations,id'],
        ]);

        $user = $request->user();
        $orgId = (int) $validated['organisation_id'];

        $organisation = $user->organisations()->whereKey($orgId)->first();

        if (! $organisation) {
            return response()->json([
                'message' => 'You are not a member of this organisation.',
                'code' => 'ORG_FORBIDDEN',
            ], 403);
        }

        $user->current_organisation_id = $orgId;
        $user->save();

        return $this->show($request);
    }
}
