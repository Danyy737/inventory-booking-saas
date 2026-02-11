<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\OrgRole;
use App\Models\Package;
use App\Models\InventoryReservation;
use App\Services\AvailabilityService;
use App\Http\Requests\PreviewBookingAvailabilityRequest;


class BookingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function ensureAdminLike($user)
    {
        if (!OrgRole::isAdminLike(OrgRole::currentRole($user))) {
            abort(response()->json(['message' => 'Forbidden.'], 403));
        }
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->current_organisation_id) {
            return response()->json(['message' => 'No active organisation selected.'], 409);
        }

        $bookings = Booking::where('organisation_id', $user->current_organisation_id)
            ->with(['reservations.item'])
            ->orderByDesc('start_at')
            ->get();

        return response()->json(['data' => $bookings]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $booking = Booking::where('organisation_id', $user->current_organisation_id)
            ->where('id', $id)
            ->with(['reservations.item'])
            ->firstOrFail();

        return response()->json(['data' => $booking]);
    }

public function store(Request $request)
{
    $user = $request->user();
    $this->ensureAdminLike($user);

    $orgId = (int) $user->current_organisation_id;

    $validated = $request->validate([
        'start_at' => ['required', 'date'],
        'end_at' => ['required', 'date', 'after:start_at'],

        'items' => ['sometimes', 'array'],
        'items.*.inventory_item_id' => ['required_with:items', 'integer'],
        'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],

        'packages' => ['sometimes', 'array'],
        'packages.*.package_id' => ['required_with:packages', 'integer'],
        'packages.*.quantity' => ['required_with:packages', 'integer', 'min:1'],
    ]);

    if (empty($validated['items']) && empty($validated['packages'])) {
        return response()->json(['message' => 'At least one item or package must be provided'], 422);
    }

    // 1) Merge direct items + expanded packages into a single requirements map
    $required = []; // [inventory_item_id => required_qty]

    foreach (($validated['items'] ?? []) as $row) {
        $itemId = (int) $row['inventory_item_id'];
        $qty = (int) $row['quantity'];
        $required[$itemId] = ($required[$itemId] ?? 0) + $qty;
    }

    foreach (($validated['packages'] ?? []) as $pkgRow) {
        $package = Package::query()
            ->where('organisation_id', $orgId)
            ->with('packageItems')
            ->findOrFail((int) $pkgRow['package_id']);

        $packageQty = (int) $pkgRow['quantity'];

        foreach ($package->packageItems as $pi) {
            $itemId = (int) $pi->inventory_item_id;
            $qty = (int) $pi->quantity * $packageQty;
            $required[$itemId] = ($required[$itemId] ?? 0) + $qty;
        }
    }

    if (empty($required)) {
        return response()->json(['message' => 'At least one item or package must be provided'], 422);
    }

    // 2) Single source of truth availability check
    $check = app(AvailabilityService::class)->check(
        $orgId,
        $validated['start_at'],
        $validated['end_at'],
        $required
    );

    if (!$check['available']) {
        return response()->json([
            'message' => 'Insufficient availability',
            'shortages' => $check['shortages'],
        ], 409);
    }

    // 3) Create booking + reservations transactionally
    $booking = DB::transaction(function () use ($validated, $orgId, $required) {
        $booking = Booking::create([
            'organisation_id' => $orgId,
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'status' => 'confirmed',
        ]);

        foreach ($required as $itemId => $qty) {
            $item = InventoryItem::query()
                ->where('organisation_id', $orgId)
                ->where('id', (int) $itemId)
                ->firstOrFail();

            $item->reservations()->create([
                'organisation_id' => $orgId,
                'booking_id' => $booking->id,
                'reserved_quantity' => (int) $qty,
                'start_at' => $validated['start_at'],
                'end_at' => $validated['end_at'],
                'status' => 'active',
            ]);
        }

        return $booking->load('reservations.item');
    });

    return response()->json(['data' => $booking], 201);
}


    public function cancel(Request $request, $id)
    {
        $user = $request->user();
        $this->ensureAdminLike($user);

        $booking = Booking::where('organisation_id', $user->current_organisation_id)
            ->where('id', $id)
            ->with('reservations')
            ->firstOrFail();

        if ($booking->end_at->isPast()) {
            return response()->json(['message' => 'Past bookings cannot be cancelled.'], 422);
        }

        if ($booking->status === 'cancelled') {
            return response()->json(['data' => $booking]);
        }

        DB::transaction(function () use ($booking) {
            $booking->update(['status' => 'cancelled']);
            $booking->reservations()->update(['status' => 'cancelled']);
        });

        return response()->json(['data' => $booking->fresh()->load('reservations')]);
    }

public function packingList(int $bookingId)
{
    $orgId = auth()->user()->current_organisation_id;

    // Tenant-safe booking lookup
    $booking = Booking::query()
        ->where('organisation_id', $orgId)
        ->findOrFail($bookingId);

    // Aggregate reservations
    $rows = InventoryReservation::query()
        ->where('booking_id', $booking->id)
        ->where('organisation_id', $orgId)
        ->selectRaw('inventory_item_id, SUM(reserved_quantity) as required_quantity')
        ->groupBy('inventory_item_id')
        ->with('item:id,name')
        ->get();

    $packingList = $rows->map(fn ($r) => [
        'inventory_item_id' => $r->inventory_item_id,
        'name' => optional($r->item)->name,
        'required_quantity' => (int) $r->required_quantity,
    ])->values();

    return response()->json([
        'booking' => [
            'id' => $booking->id,
            'status' => $booking->status,
            'start_at' => $booking->start_at,
            'end_at' => $booking->end_at,
        ],
        'packing_list' => $packingList,
        'summary' => [
            'unique_items' => $packingList->count(),
            'total_units' => $packingList->sum('required_quantity'),
        ],
    ]);
}
public function previewAvailability(PreviewBookingAvailabilityRequest $request)
{
    $data = $request->validated();
    $orgId = (int) auth()->user()->current_organisation_id;

    // required: [inventory_item_id => total_required_qty]
    $required = [];

    // A) Expand packages into required inventory items
    foreach (($data['packages'] ?? []) as $p) {
        $packageId = (int) $p['package_id'];
        $packageQty = (int) $p['quantity'];

        $package = Package::query()
            ->where('organisation_id', $orgId)
            ->with('packageItems')
            ->findOrFail($packageId);

        foreach ($package->packageItems as $pi) {
            $itemId = (int) $pi->inventory_item_id;
            $qty = (int) $pi->quantity * $packageQty;

            $required[$itemId] = ($required[$itemId] ?? 0) + $qty;
        }
    }

    // B) Merge add-on items into the same requirements map
    foreach (($data['items'] ?? []) as $i) {
        $itemId = (int) $i['inventory_item_id'];
        $qty = (int) $i['quantity'];

        $required[$itemId] = ($required[$itemId] ?? 0) + $qty;
    }

    // If nothing required, return early
    if (empty($required)) {
        return response()->json([
            'available' => true,
            'window' => [
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'],
            ],
            'requirements' => [],
            'availability' => [],
            'shortages' => [],
        ]);
    }

    // Load names for output (tenant-safe)
    $itemsById = InventoryItem::query()
        ->where('organisation_id', $orgId)
        ->whereIn('id', array_keys($required))
        ->get(['id', 'name'])
        ->keyBy('id');

    $requirements = collect($required)->map(function ($qty, $itemId) use ($itemsById) {
        return [
            'inventory_item_id' => (int) $itemId,
            'name' => $itemsById->get((int) $itemId)?->name,
            'required_quantity' => (int) $qty,
        ];
    })->values();

    // Phase 3 via shared engine
    $result = app(AvailabilityService::class)->check(
        $orgId,
        $data['start_at'],
        $data['end_at'],
        $required
    );

    // Attach names to engine output
    $names = $requirements->keyBy('inventory_item_id');

    $availability = collect($result['availability'])->map(function ($row) use ($names) {
        $name = $names->get((int) $row['inventory_item_id'])['name'] ?? null;

        return [
            'inventory_item_id' => (int) $row['inventory_item_id'],
            'name' => $name,
            'required_quantity' => (int) $row['required_quantity'],
            'available_quantity' => (int) $row['available_quantity'],
            'short_by' => (int) $row['short_by'],
        ];
    })->values();

    $shortages = collect($result['shortages'])->map(function ($row) use ($names) {
        $name = $names->get((int) $row['inventory_item_id'])['name'] ?? null;

        return [
            'inventory_item_id' => (int) $row['inventory_item_id'],
            'name' => $name,
            'required_quantity' => (int) $row['required_quantity'],
            'available_quantity' => (int) $row['available_quantity'],
            'short_by' => (int) $row['short_by'],
        ];
    })->values();

    return response()->json([
        'available' => (bool) $result['available'],
        'window' => [
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
        ],
        'requirements' => $requirements,
        'availability' => $availability,
        'shortages' => $shortages,
    ]);
}









}
