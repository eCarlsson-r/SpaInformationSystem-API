<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Bed;
use App\Models\BookingPayment;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'branch_id' => 'sometimes|integer|exists:branches,id',
            'date' => 'sometimes|date',
            'time' => 'sometimes|date_format:H:i',
            'duration' => 'sometimes|integer|min:1|max:1440',
        ]);
        $rooms = Room::with(['branch', 'bed'])
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->input('branch_id')))
            ->get();

        if ($request->input('show') === 'empty') {
            $date = $request->input('date');
            $time = $request->input('time');
            $duration = (int) $request->input('duration', 60);

            if ($date && $time) {
                $start = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}");
                $end = $start->copy()->addMinutes($duration);
                $sessionStatuses = ['waiting', 'ongoing'];
                $busyBedIds = Session::query()
                    ->whereDate('date', $start->toDateString())
                    ->whereIn('status', $sessionStatuses)
                    ->where('start', '<', $end->format('H:i:s'))
                    ->where('end', '>', $start->format('H:i:s'))
                    ->pluck('bed_id')
                    ->all();
                $heldBedIds = BookingPayment::query()
                    ->whereDate('session_date', $start->toDateString())
                    ->whereIn('status', ['creating', 'pending'])
                    ->where('expires_at', '>', now())
                    ->where('session_start', '<', $end->format('H:i:s'))
                    ->where('session_end', '>', $start->format('H:i:s'))
                    ->pluck('bed_id')
                    ->all();
                $unavailableBedIds = array_unique([...$busyBedIds, ...$heldBedIds]);
            } else {
                $unavailableBedIds = Session::query()
                    ->where('status', 'ongoing')
                    ->pluck('bed_id')
                    ->all();
            }

            return $rooms->map(function ($room) use ($unavailableBedIds) {
                $availableBeds = $room->bed->reject(fn ($bed) => in_array($bed->id, $unavailableBedIds));
                $room->setRelation('bed', $availableBeds->values());

                return $room;
            })->filter(fn ($room) => $room->bed->isNotEmpty())->values();
        } else {
            $ongoingBedIds = Session::query()->where('status', 'ongoing')->pluck('bed_id');
            return $rooms->map(function ($room) use ($ongoingBedIds) {
                $occupied_beds = $room->bed->whereIn('id', $ongoingBedIds)->count();

                $room->occupied = $occupied_beds;
                $room->empty = $room->bed->count() - $occupied_beds;

                return $room;
            });
        }
    }

    public function available(Request $request)
    {
        $request->merge(['show' => 'empty']);
        return $this->index($request);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'beds' => 'required|array',
            'beds.*.name' => 'required|string|max:255'
        ]);

        $room = Room::create([
            'name' => $request->name,
            'branch_id' => $request->branch_id,
            'description' => $request->description,
        ]);

        $room->bed()->createMany($request->beds);

        if ($room) {
            return response()->json($room, 201);
        } else {
            return response()->json(['message' => 'Failed to create room'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request,string $id)
    {
        if ($request->has('show') && $request->show == 'session') {
            return Room::with('bed.session.employee', 'bed.session.treatment')->findOrFail($id);
        } else {
            return Room::with('bed')->findOrFail($id);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Room $room)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'beds' => 'required|array',
            'beds.*.id' => 'nullable', // Allow null for new beds
            'beds.*.name' => 'required|string|max:255'
        ]);

        try {
            return DB::transaction(function () use ($request, $room) {
                // 1. Update the Room
                $room->update([
                    'name' => $request->name,
                    'branch_id' => $request->branch_id,
                    'description' => $request->description,
                ]);

                // 2. Identify which beds to keep (those with IDs)
                $incomingBedIds = collect($request->beds)->pluck('id')->filter()->toArray();

                // 3. Delete beds that were removed in the UI
                $room->beds()->whereNotIn('id', $incomingBedIds)->delete();

                // 4. Update existing beds or Create new ones
                foreach ($request->beds as $bedData) {
                    $room->beds()->updateOrCreate(
                        ['id' => $bedData['id'] ?? null], // Match by ID
                        ['name' => $bedData['name']]      // Data to update/create
                    );
                }

                // Return the room with the refreshed bed list
                return response()->json($room->load('beds'), 200);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update room and beds',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Room $room)
    {
        if ($room->delete()) {
            return response()->json(['message' => 'Room deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete room'], 500);
        }
    }
}
