<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if (!$user->current_organisation_id) {
            return response()->json([
                'message' => 'No active organisation selected.',
            ], 409);
        }

        $organisation = $user->organisations()
            ->whereKey($user->current_organisation_id)
            ->first();

        if (!$organisation) {
            return response()->json([
                'message' => 'You are not a member of the active organisation.',
            ], 403);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'current_organisation' => [
                'id' => $organisation->id,
                'name' => $organisation->name,
                'slug' => $organisation->slug,
            ],
            'role' => $organisation->pivot->role,
        ]);
    }
}
