<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\JoinCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class OrganisationOnboardingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $org = DB::transaction(function () use ($validated, $user) {
            $org = Organisation::create([
                'name' => $validated['name'],
                'join_code' => JoinCodeGenerator::generate(8),
                'join_enabled' => true,
                'slug' => $this->generateUniqueSlug($validated['name']),
            ]);

            // creator becomes owner
            $user->organisations()->attach($org->id, ['role' => 'owner']);

            // set tenant context
            $user->forceFill(['current_organisation_id' => $org->id])->save();

            return $org;
        });

        return response()->json([
            'organisation' => $org,
        ], 201);
    }

    public function join(Request $request)
    {
        $validated = $request->validate([
            'join_code' => ['required', 'string', 'max:12'],
        ]);

        $user = $request->user();
        $code = strtoupper(trim($validated['join_code']));

        $org = Organisation::where('join_code', $code)->first();

        if (!$org) {
            return response()->json(['message' => 'Invalid join code.'], 404);
        }

        if (!$org->join_enabled) {
            return response()->json(['message' => 'Joining is disabled for this organisation.'], 403);
        }

        DB::transaction(function () use ($user, $org) {
            $alreadyMember = $user->organisations()
                ->where('organisations.id', $org->id)
                ->exists();

            if (!$alreadyMember) {
                $user->organisations()->attach($org->id, ['role' => 'staff']);
            }

            $user->forceFill(['current_organisation_id' => $org->id])->save();
        });

        return response()->json([
            'organisation' => $org,
        ], 200);
    }

    private function generateUniqueSlug(string $name): string
{
    $base = Str::slug($name);

    // Fallback if name becomes empty after slugging (rare)
    if ($base === '') {
        $base = 'org';
    }

    $slug = $base;
    $i = 2;

    while (Organisation::where('slug', $slug)->exists()) {
        $slug = "{$base}-{$i}";
        $i++;
    }

    return $slug;
}

}
