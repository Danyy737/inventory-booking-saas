<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MyOrganisationsController extends Controller
{
    public function index(Request $request)
    {
        $orgs = $request->user()
            ->organisations()
            ->select('organisations.id', 'organisations.name', 'organisations.slug')
            ->orderBy('organisations.name')
            ->get()
            ->map(function ($org) {
                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'role' => $org->pivot->role ?? null,
                ];
            });

        return response()->json(['data' => $orgs]);
    }
}
