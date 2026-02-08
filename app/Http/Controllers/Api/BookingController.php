<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List bookings for the current organisation
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->current_organisation_id) {
            return response()->json(['message' => 'No active organisation selected.'], 409);
        }

        $orgId = (int) $user->current_organisation_id;

        $bookings = Booking::query()
            ->where('organisation_id', $orgId)
            ->with([
                'reservations:id,booking_id,organisation_id,inventory_item_id,reserved_quantity,start_at,end_at,status',
                'reservations.item:id,organisation_id,name,sku,is_active',
            ])
            ->orderByDesc('start_at')
            ->get();

        return response()->json([
            'data' => $bookings,
        ]);
    }

    /**
     * Show a single booking
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->current_organisation_id) {
            return response()->json(['message' => 'No active organisation selected.'], 409);
        }

        $orgId = (int) $user->current_organisation_id;

        $booking = Booking::query()
            ->where('organisation_id', $orgId)
            ->where('id', $id)
            ->with([
                'reservations:id,booking_id,organisation_id,inventory_item_id,reserved_quantity,start_at,end_at,status',
                'reservations.item:id,organisation_id,name,sku,is_active',
            ])
            ->firstOrFail();

        return response()->json([
            'data' => $booking,
        ]);
    }

    /**
     * Create a booking + reservations
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->current_organisation_id) {
            return response()->json(['message' => 'No active organisation selected.'], 409);
        }

        $orgId = (int) $user->current_organisation_id;

        $validated = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $startAt = $validated['start_at'];
        $endAt = $validated['end_at'];

        $booking = DB::transaction(function () use ($validated, $orgId, $startAt, $endAt) {
            $booking = Booking::create([
                'organisation_id' => $orgId,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => 'confirmed',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $row) {
                $item = InventoryItem::query()
                    ->where('organisation_id', $orgId)
                    ->where('id', $row['inventory_item_id'])
                    ->with('stock')
                    ->firstOrFail();

                $total = (int) optional($item->stock)->total_quantity;

                $reserved = (int) $item->reservations()
                    ->where('organisation_id', $orgId)
                    ->where('status', 'active')
                    ->where('start_at', '<', $endAt)
                    ->where('end_at', '>', $startAt)
                    ->sum('reserved_quantity');

                $remaining = $total - $reserved;

                if ($remaining < $row['quantity']) {
                    abort(response()->json([
                        'message' => 'Insufficient availability',
                        'inventory_item_id' => $item->id,
                        'remaining' => $remaining,
                    ], 409));
                }

                $item->reservations()->create([
                    'organisation_id' => $orgId,
                    'booking_id' => $booking->id,
                    'reserved_quantity' => $row['quantity'],
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'status' => 'active',
                ]);
            }

            return $booking->load('reservations');
        });

        return response()->json([
            'data' => $booking,
        ], 201);
    }

    public function cancel(Request $request, $id)
{
    $user = $request->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    if (!$user->current_organisation_id) {
        return response()->json(['message' => 'No active organisation selected.'], 409);
    }

    $orgId = (int) $user->current_organisation_id;

    $booking = Booking::query()
        ->where('organisation_id', $orgId)
        ->where('id', $id)
        ->with('reservations')
        ->firstOrFail();

    // 🚫 Rule: cannot cancel bookings that have already ended
    if ($booking->end_at->isPast()) {
        return response()->json([
            'message' => 'Past bookings cannot be cancelled.',
        ], 422);
    }

    // Idempotent: cancelling twice is OK
    if ($booking->status === 'cancelled') {
        return response()->json([
            'data' => $booking->load('reservations'),
        ]);
    }

    \DB::transaction(function () use ($booking) {
        $booking->update(['status' => 'cancelled']);
        $booking->reservations()->update(['status' => 'cancelled']);
    });

    return response()->json([
        'data' => $booking->fresh()->load('reservations'),
    ]);
}


public function update(Request $request, $id)
{
    $user = $request->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    if (!$user->current_organisation_id) {
        return response()->json(['message' => 'No active organisation selected.'], 409);
    }

    $orgId = (int) $user->current_organisation_id;

    $booking = Booking::query()
        ->where('organisation_id', $orgId)
        ->where('id', $id)
        ->with('reservations')
        ->firstOrFail();

    // 🚫 Cannot edit cancelled or past bookings
    if ($booking->status === 'cancelled' || $booking->end_at->isPast()) {
        return response()->json([
            'message' => 'This booking cannot be edited.',
        ], 422);
    }

    $validated = $request->validate([
        'start_at' => ['required', 'date'],
        'end_at' => ['required', 'date', 'after:start_at'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.inventory_item_id' => ['required', 'integer'],
        'items.*.quantity' => ['required', 'integer', 'min:1'],
    ]);

    $startAt = $validated['start_at'];
    $endAt = $validated['end_at'];

    $updated = \DB::transaction(function () use ($booking, $validated, $orgId, $startAt, $endAt) {

        // 1️⃣ Availability check (ignore this booking’s own reservations)
        foreach ($validated['items'] as $row) {
            $item = InventoryItem::query()
                ->where('organisation_id', $orgId)
                ->where('id', $row['inventory_item_id'])
                ->with('stock')
                ->firstOrFail();

            $total = (int) optional($item->stock)->total_quantity;

            $reserved = (int) $item->reservations()
                ->where('organisation_id', $orgId)
                ->where('status', 'active')
                ->where('booking_id', '!=', $booking->id) // 🔑 ignore self
                ->where('start_at', '<', $endAt)
                ->where('end_at', '>', $startAt)
                ->sum('reserved_quantity');

            if (($total - $reserved) < $row['quantity']) {
                abort(response()->json([
                    'message' => 'Insufficient availability',
                    'inventory_item_id' => $item->id,
                ], 409));
            }
        }

        // 2️⃣ Update booking window
        $booking->update([
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);

        // 3️⃣ Replace reservations
        $booking->reservations()->delete();

        foreach ($validated['items'] as $row) {
            $booking->reservations()->create([
                'organisation_id' => $orgId,
                'inventory_item_id' => $row['inventory_item_id'],
                'reserved_quantity' => $row['quantity'],
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => 'active',
            ]);
        }

        return $booking->load('reservations');
    });

    return response()->json([
        'data' => $updated,
    ]);
}



}
