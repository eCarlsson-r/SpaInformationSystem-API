<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use Illuminate\Http\Request;
use Carbon\Carbon;

class JournalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->has("start") && $request->has("end") && $request->has("account")) {
            $startDate = Carbon::parse($request->input("start"))->toDateString();
            $endDate = Carbon::parse($request->input("end"))->toDateString();

            return Journal::join('journal_records', 'journals.id', '=', 'journal_records.journal_id')
                ->whereBetween('journals.date', [$startDate, $endDate])
                ->where("journal_records.account_id", $request->input("account"))
                ->selectRaw('SUM(journal_records.debit - journal_records.credit) AS `earning`')
                ->orderBy('journals.date')
                ->get();
        } else {
            return Journal::all();
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $journal = Journal::create($request->all());

        $journal->records()->createMany($request->records);

        if ($journal) {
            return response()->json($journal, 201);
        } else {
            return response()->json(['message' => 'Failed to create journal'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(String $id)
    {
        return Journal::with('records')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Journal $journal)
    {
        $request->validate([
            'reference' => 'required',
            'date' => 'required',
            'description' => 'required|string|max:255',
            'records' => 'required|array',
            'records.*.id' => 'nullable', // Allow null for new records
            'records.*.description' => 'required|string|max:255',
            'records.*.debit' => 'required|numeric',
            'records.*.credit' => 'required|numeric',
        ]);

        try {
            return DB::transaction(function () use ($request, $journal) {
                // 1. Update the Journal
                $journal->update([
                    'reference' => $request->reference,
                    'date' => $request->date,
                    'description' => $request->description,
                ]);

                // 2. Identify which records to keep (those with IDs)
                $incomingRecordIds = collect($request->records)->pluck('id')->filter()->toArray();

                // 3. Delete records that were removed in the UI
                $journal->records()->whereNotIn('id', $incomingRecordIds)->delete();

                // 4. Update existing records or Create new ones
                foreach ($request->records as $recordData) {
                    $journal->records()->updateOrCreate(
                        ['id' => $recordData['id'] ?? null], // Match by ID
                        [
                            'description' => $recordData['description'],
                            'debit' => $recordData['debit'],
                            'credit' => $recordData['credit'],
                        ]
                    );
                }

                // Return the journal with the refreshed records list
                return response()->json($journal->load('records'), 200);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update journal and records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Journal $journal)
    {
        if ($journal->delete()) {
            return response()->json(['message' => 'Journal deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete journal'], 500);
        }
    }
}
