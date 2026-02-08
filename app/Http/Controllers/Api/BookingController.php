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

            // items: [{ inventory_item_id: 1, quantity: 2 }, ...]
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $startAt = $validated['start_at'];
        $endAt = $validated['end_at'];

        $result = DB::transaction(function () use ($validated, $orgId, $startAt, $endAt) {
            // 1) Create booking first (we can rollback if availability fails)
            $booking = Booking::create([
                'organisation_id' => $orgId,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => 'confirmed',
                'notes' => $validated['notes'] ?? null,
            ]);

            // 2) Check each item availability, then create reservation rows
            foreach ($validated['items'] as $row) {
                $itemId = (int) $row['inventory_item_id'];
                $qty = (int) $row['quantity'];

                $item = InventoryItem::query()
                    ->where('organisation_id', $orgId)
                    ->where('id', $itemId)
                    ->with('stock')
                    ->firstOrFail();

                $total = (int) optional($item->stock)->total_quantity;

                $reserved = (int) $item->reservations()
                    ->where('organisation_id', $orgId)
                    ->where('status', 'active')
                    ->where('start_at', '<', $endAt)  // overlap rule
                    ->where('end_at', '>', $startAt)  // overlap rule
                    ->sum('reserved_quantity');

                $remaining = max(0, $total - $reserved);

                if ($remaining < $qty) {
                    // Fail the whole transaction
                    abort(response()->json([
                        'message' => 'Insufficient availability for one or more items.',
                        'data' => [
                            'inventory_item_id' => $itemId,
                            'requested_quantity' => $qty,
                            'remaining_quantity' => $remaining,
                            'start_at' => $startAt,
                            'end_at' => $endAt,
                        ],
                    ], 409));
                }

                // Create reservation linked to this booking
                $item->reservations()->create([
                    'organisation_id' => $orgId,
                    'booking_id' => $booking->id,
                    'reserved_quantity' => $qty,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'status' => 'active',
                ]);
            }

            return $booking->load('reservations');
        });

        return response()->json([
            'data' => $result,
        ], 201);
    }
}
