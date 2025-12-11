<?php

namespace App\Http\Controllers;

use App\Models\JournalRecord;
use Illuminate\Http\Request;

class JournalRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return JournalRecord::where('journal_id', $request->journal_id)->get();
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
    public function show(JournalRecord $journalRecord)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, JournalRecord $journalRecord)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $journalRecord = JournalRecord::find($id);
        if ($journalRecord->delete()) {
            return response()->json(['message' => 'Journal record deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete journal record'], 500);
        }
    }
}