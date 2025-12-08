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
        $rooms = Room::with(['branch', 'bed.sessions' => function ($query) {
            $query->where('status', 'ongoing');
        }])->get();

        return $rooms->map(function ($room) {
            $occupied_beds = $room->bed->filter(function ($bed) {
                return $bed->sessions->isNotEmpty();
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

        return response()->json([
            'message' => 'Room created successfully',
            'room' => $room,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Room::findOrFail($id);
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
            'beds.*.name' => 'required|string|max:255'
        ]);

        $room->update([
            'name' => $request->name,
            'branch_id' => $request->branch_id,
            'description' => $request->description,
        ]);

        foreach ($request->beds as $bed) {
            Bed::updateOrCreate([
                'id' => $bed['id'],
            ], $bed);
        }

        return response()->json([
            'message' => 'Room updated successfully',
            'room' => $room,
        ], 200);
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
