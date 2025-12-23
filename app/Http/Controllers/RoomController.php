<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Bed;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $rooms = Room::with(['branch', 'bed.session' => function ($query) {
            $query->where('status', 'ongoing');
        }])->get();

        return $rooms->map(function ($room) {
            $occupied_beds = $room->bed->filter(function ($bed) {
                return $bed->session;
            })->count();

            $room->occupied = $occupied_beds;
            $room->empty = $room->bed->count() - $occupied_beds;

            return $room;
        });
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
