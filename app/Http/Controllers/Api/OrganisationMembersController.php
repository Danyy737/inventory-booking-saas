<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Support\OrgRole;
use Illuminate\Support\Facades\DB;

class OrganisationMembersController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!OrgRole::isAdminLike(OrgRole::currentRole($user))) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $orgId = (int) $user->current_organisation_id;

        $members = DB::table('users')
            ->join('organisation_user', 'organisation_user.user_id', '=', 'users.id')
            ->where('organisation_user.organisation_id', $orgId)
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.name',
                'users.email',
                'organisation_user.role',
            ]);

        return response()->json(['data' => $members]);
    }
}
