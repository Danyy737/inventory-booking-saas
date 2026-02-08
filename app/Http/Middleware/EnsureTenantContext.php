<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // auth:sanctum should handle unauthenticated, but we keep this defensive.
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $orgId = $user->current_organisation_id;

        if (! $orgId) {
            return response()->json(['message' => 'No current organisation selected.'], 409);
        }

        // Confirm membership + fetch role from pivot
        $org = $user->organisations()
            ->where('organisations.id', $orgId)
            ->first();

        if (! $org) {
            return response()->json(['message' => 'You are not a member of your current organisation.'], 403);
        }

        $role = $org->pivot->role;

        // Attach tenant context for controllers to use
        $request->attributes->set('currentOrganisation', $org);
        $request->attributes->set('currentOrgRole', $role);

        // Optional: keep user relation consistent too
        $user->setRelation('currentOrganisation', $org);

        return $next($request);
    }
}
