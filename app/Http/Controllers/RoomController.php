<?php

namespace App\Http\Controllers;

use App\Models\Room;
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
        //
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
        //
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
