<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Agent::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $account = Agent::create($request->all());
        
        if ($account) {
            return response()->json($account, 201);
        } else {
            return response()->json(['message' => 'Failed to create account'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Agent::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Agent $agent)
    {
        if ($agent->update($request->all())) {
            return response()->json($agent, 200);
        } else {
            return response()->json(['message' => 'Failed to update agent'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Agent $agent)
    {
        if ($agent->delete()) {
            return response()->json(['message' => 'Agent deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete agent'], 500);
        }
    }
}
