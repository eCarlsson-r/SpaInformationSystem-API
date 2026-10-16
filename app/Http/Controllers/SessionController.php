<?php

namespace App\Http\Controllers;

use App\Events\FeedbackPrompt;
use App\Models\User;
use App\Models\Session;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\Journal;
use App\Models\Walkin;
use App\Models\Voucher;
use App\Notifications\SessionMade;
use App\Services\SessionSlotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SessionController extends Controller
{
    public function __construct(private readonly SessionSlotService $slotService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->input("variant") == "walkin-voucher-usage") {
            $this->requireStaff($request);
            $fromDate = Carbon::parse($request->input("start"))->toDateString();
            $toDate = Carbon::parse($request->input("end"))->toDateString();
            
            return Session::join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
                ->join('employees', 'sessions.employee_id', '=', 'employees.id')
                ->join('beds', 'sessions.bed_id', '=', 'beds.id')
                ->join('rooms', 'beds.room_id', '=', 'rooms.id')
                ->leftJoin('voucher', 'sessions.id', '=', 'voucher.session_id')
                ->leftJoin('walkin', 'sessions.id', '=', 'walkin.session_id')
                ->leftJoin('sales', 'walkin.sales_id', '=', 'sales.id')
                ->leftJoin('sales_records', function($join) {
                    $join->on('sales.id', '=', 'sales_records.sales_id')
                         ->on('sales_records.treatment_id', '=', 'sessions.treatment_id');
                })
                ->leftJoin('incomes', 'sales.income_id', '=', 'incomes.id')
                ->selectRaw("
                    sessions.date, 
                    sessions.start as time,
                    MAX(IF(sessions.payment = 'voucher', voucher.id, incomes.journal_reference)) as `reference`,
                    employees.name as `therapist_name`,
                    treatments.name as description,
                    MAX(IF(sessions.payment = 'voucher', voucher.amount, ROUND((sales_records.price - (COALESCE(sales_records.discount, 0)))/1000)*1000)) as `price`
                ")
                ->whereBetween('sessions.date', [$fromDate, $toDate])
                ->whereIn('sessions.status', ['completed', 'ongoing'])
                ->when($this->staffBranchId($request) !== null, fn ($query) => $query->where('rooms.branch_id', $this->staffBranchId($request)))
                ->where(function($query) {
                    $query->where('voucher.amount', '>', 0)
                          ->orWhere('sales_records.price', '>', 0);
                })
                ->groupBy('sessions.id', 'sessions.date', 'sessions.start', 'employees.name', 'treatments.name')
                ->orderBy('sessions.id')
                ->get();
        } else if (auth()->user()->customer) {
            return Session::with('treatment', 'employee', 'bed', 'bed.room.branch')
                ->where('sessions.customer_id', auth()->user()->customer->id)
                ->where('sessions.status', 'waiting')
                ->get();
        } else {
            $this->requireStaff($request);
            if (is_string($request->input('status'))) {
                $request->merge(['status' => json_decode($request->input('status'), true)]);
            }
            $statuses = $request->validate(['status' => 'required|array', 'status.*' => 'in:waiting,ongoing,completed,cancelled'])['status'];
            $sessions = Session::whereIn('sessions.status', $statuses)
                ->join('treatments', 'sessions.treatment_id', '=', 'treatments.id')
                ->join('customers', 'sessions.customer_id', '=', 'customers.id')
                ->join('employees', 'sessions.employee_id', '=', 'employees.id')
                ->join('beds', 'sessions.bed_id', '=', 'beds.id')
                ->leftJoin('walkin', 'walkin.session_id', '=', 'sessions.id')
                ->leftJoin('voucher', 'voucher.session_id', '=', 'sessions.id')
                ->leftJoin('sales', 'sales.id', '=', 'walkin.sales_id')
                ->leftJoin('incomes', 'incomes.id', '=', 'sales.income_id')
                ->select(
                    'sessions.*',
                    'customers.name AS customer_name', 
                    'treatments.name AS treatment_name', 
                    'treatments.duration AS treatment_duration', 
                    'employees.name AS therapist_name', 
                    'beds.name AS bed_name',
                    'walkin.id AS walkin_id',
                    'voucher.id AS voucher_id',
                    'incomes.journal_reference AS reference'
                );
            
            $branchId = $this->staffBranchId($request);
            if ($branchId !== null) {
                $sessions->whereHas('bed.room', fn ($query) => $query->where('rooms.branch_id', $branchId));
            } elseif ($request->filled('branch_id')) {
                $sessions->whereHas('bed.room', fn ($query) => $query->where('rooms.branch_id', $request->input('branch_id')));
            }
            if ($request->input('employee_id')) {
                $sessions->where('sessions.employee_id', $request->input('employee_id'));
            } elseif ($request->input("start") && $request->input("end") && $request->input("from_employee") && $request->input("to_employee") && $request->input("order_by")) {
                $sessions->whereBetween('sessions.date', [
                    Carbon::parse($request->input("start"))->toDateString(), 
                    Carbon::parse($request->input("end"))->toDateString()
                ])
                    ->whereBetween('sessions.employee_id', [$request->input("from_employee"), $request->input("to_employee")])
                    ->orderBy(in_array($request->input('order_by'), ['sessions.date', 'sessions.start', 'sessions.id'], true) ? $request->input('order_by') : 'sessions.id');
            }
            return $sessions->get();
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->requireStaff($request);
        $data = $request->validate([
            'bed_id' => 'required|exists:beds,id',
            'customer_id' => 'required|exists:customers,id',
            'employee_id' => 'required|exists:employees,id',
            'treatment_id' => 'required|exists:treatments,id',
            'payment' => 'required|string',
            'date' => 'nullable|date',
            'start' => 'nullable|string',
            'end' => 'nullable|string',
            'walkin_id' => 'nullable|integer|exists:walkin,id',
            'voucher_id' => 'nullable|integer|exists:voucher,id',
        ]);
        $bedId = (int) $data['bed_id'];
        $employeeId = (int) $data['employee_id'];
        $this->assertSessionBranch($request, $bedId, $employeeId);

        $startAt = filled($data['start'] ?? null) ? Carbon::parse($data['start']) : null;
        $date = isset($data['date'])
            ? Carbon::parse($data['date'])->toDateString()
            : ($startAt?->toDateString() ?? now()->toDateString());
        $start = $startAt?->format('H:i:s');
        $treatment = \App\Models\Treatment::findOrFail($data['treatment_id']);
        $endAt = filled($data['end'] ?? null)
            ? Carbon::parse($data['end'])
            : $startAt?->copy()->addMinutes((int) $treatment->duration);
        $end = $endAt?->format('H:i:s');

        $walkin = isset($data['walkin_id']) ? Walkin::with('sales')->findOrFail($data['walkin_id']) : null;
        $voucher = isset($data['voucher_id']) ? Voucher::with('sales.income', 'sales.branch')->findOrFail($data['voucher_id']) : null;
        $salesId = $walkin?->sales_id ?? $voucher?->sales_id;

        $session = DB::transaction(function () use ($data, $bedId, $employeeId, $date, $start, $end, $walkin, $voucher, $salesId) {
            $this->slotService->lockResources($employeeId, $bedId);
            if ($start !== null && $end !== null) {
                $this->slotService->assertAvailable($employeeId, $bedId, $date, $start, $end);
            }

            $session = Session::create([
                'order_time' => now()->format('H:i:s'),
                'bed_id' => $bedId,
                'customer_id' => $data['customer_id'],
                'payment' => $data['payment'],
                'date' => $date,
                'start' => $start,
                'end' => $end,
                'employee_id' => $employeeId,
                'treatment_id' => $data['treatment_id'],
                'sales_id' => $salesId,
                'status' => $start !== null ? 'ongoing' : 'waiting',
            ]);

            if ($voucher) {
                $voucher->update(['session_id' => $session->id]);
                $journal = Journal::where('reference', $voucher->sales->income->journal_reference)->firstOrFail();
                $journal->records()->create([
                    'account_id' => $voucher->sales->branch->voucher_usage_account,
                    'debit' => $voucher->amount,
                    'credit' => 0,
                    'description' => "Treat Sess [{$session->id}, {$session->customer->name}, Voucher No. {$voucher->id}]",
                ]);
            } elseif ($walkin) {
                $walkin->update(['session_id' => $session->id]);
            }

            return $session;
        }, 3);

        if ($session) {
            $employee = Employee::find($employeeId)->user;
            if ($employee) $employee->notify(new SessionMade($session));
            $customer = Customer::find($data['customer_id'])->user;
            if ($customer) $customer->notify(new SessionMade($session));
            $admin = User::find(1);
            if ($admin) $admin->notify(new SessionMade($session));
            return response()->json($session, 201);
        } else {
            return response()->json(['message' => 'Failed to create session'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $session = Session::with('bed.room')->findOrFail($id);
        $request = request();
        if (!$request->user()->customer) {
            $this->requireStaff($request);
            $branchId = $this->staffBranchId($request);
            abort_unless($branchId === null || (int) $session->bed?->room?->branch_id === $branchId, 404);
        } else {
            abort_unless((int) $session->customer_id === (int) $request->user()->customer->id, 404);
        }
        return $session;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Session $session)
    {
        $this->requireStaff($request);
        $data = $request->validate([
            'bed_id' => 'sometimes|exists:beds,id',
            'employee_id' => 'sometimes|exists:employees,id',
            'customer_id' => 'sometimes|exists:customers,id',
            'treatment_id' => 'sometimes|exists:treatments,id',
            'payment' => 'sometimes|string',
            'date' => 'sometimes|nullable|date',
            'start' => 'sometimes|nullable|string',
            'end' => 'sometimes|nullable|string',
            'status' => 'sometimes|in:waiting,ongoing,completed,cancelled',
        ]);

        $session = DB::transaction(function () use ($request, $session, $data) {
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $bedId = (int) ($data['bed_id'] ?? $session->bed_id);
            $employeeId = (int) ($data['employee_id'] ?? $session->employee_id);
            $this->assertSessionBranch($request, $bedId, $employeeId);
            $this->slotService->lockResources($employeeId, $bedId);

            $treatmentId = (int) ($data['treatment_id'] ?? $session->treatment_id);
            $date = isset($data['date']) ? Carbon::parse($data['date'])->toDateString() : $session->date;
            $startAt = array_key_exists('start', $data)
                ? (filled($data['start']) ? Carbon::parse($data['start']) : null)
                : ($session->start ? Carbon::parse($session->start) : null);
            $endAt = array_key_exists('end', $data)
                ? (filled($data['end']) ? Carbon::parse($data['end']) : null)
                : ($session->end ? Carbon::parse($session->end) : null);
            if ($startAt && !$endAt) {
                $duration = (int) \App\Models\Treatment::whereKey($treatmentId)->value('duration');
                $endAt = $startAt->copy()->addMinutes($duration);
            }

            if ($startAt && $endAt) {
                $this->slotService->assertAvailable(
                    $employeeId,
                    $bedId,
                    $date,
                    $startAt->format('H:i:s'),
                    $endAt->format('H:i:s'),
                    $session->id,
                );
            }

            $data['bed_id'] = $bedId;
            $data['employee_id'] = $employeeId;
            $data['date'] = $date;
            $data['start'] = $startAt?->format('H:i:s');
            $data['end'] = $endAt?->format('H:i:s');
            $session->update($data);

            return $session;
        }, 3);

        if ($session) {
            return response()->json($session, 200);
        } else {
            return response()->json(['message' => 'Failed to update session'], 500);
        }
    }

    public function start(Session $session)
    {
        $request = request();
        $this->requireStaff($request);
        $session = DB::transaction(function () use ($request, $session) {
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->assertSessionBranch($request, (int) $session->bed_id, (int) $session->employee_id);
            $this->slotService->lockResources((int) $session->employee_id, (int) $session->bed_id);

            $start = now();
            $end = $start->copy()->addMinutes((int) $session->treatment()->value('duration'));
            $this->slotService->assertAvailable(
                (int) $session->employee_id,
                (int) $session->bed_id,
                $start->toDateString(),
                $start->format('H:i:s'),
                $end->format('H:i:s'),
                $session->id,
            );

            $session->update([
                'status' => 'ongoing',
                'date' => $start->toDateString(),
                'start' => $start->format('H:i:s'),
                'end' => $end->format('H:i:s'),
            ]);

            return $session;
        }, 3);

        if ($session) {
            return response()->json($session, 200);
        } else {
            return response()->json(['message' => 'Failed to start session'], 500);
        }
    }

    public function finish(Session $session)
    {
        $request = request();
        $this->requireStaff($request);
        $this->assertSessionBranch($request, (int) $session->bed_id, (int) $session->employee_id);
        $session->update([
            'status' => 'completed',
            'end' => now(),
        ]);

        if ($session) {
            // Requirement 9.1: broadcast FeedbackPrompt to the customer's private channel
            if ($session->customer_id) {
                try {
                    broadcast(new FeedbackPrompt($session, (int) $session->customer_id));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('SessionController: Failed to broadcast FeedbackPrompt', [
                        'session_id'  => $session->id,
                        'customer_id' => $session->customer_id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            return response()->json($session, 200);
        } else {
            return response()->json(['message' => 'Failed to finish session'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Session $session)
    {
        $request = request();
        if ($request->user()->customer) {
            abort_unless((int) $session->customer_id === (int) $request->user()->customer->id && $session->status === 'waiting', 404);
            $session->delete();
            return response()->json(['message' => 'Session deleted successfully'], 200);
        }
        $this->requireStaff($request);
        $this->assertSessionBranch($request, (int) $session->bed_id, (int) $session->employee_id);
        if ($session->delete()) {
            return response()->json(['message' => 'Session deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Failed to delete session'], 500);
        }
    }

    private function requireStaff(Request $request): void
    {
        abort_unless(in_array(strtoupper((string) $request->user()->type), ['ADMIN', 'MANAGER', 'STAFF', 'CASHIER', 'THERAPIST'], true), 403);
        if (strtoupper((string) $request->user()->type) !== 'ADMIN') {
            abort_unless($request->user()->employee?->branch_id, 403, 'A branch assignment is required.');
        }
    }

    private function staffBranchId(Request $request): ?int
    {
        return strtoupper((string) $request->user()->type) === 'ADMIN' ? null : (int) $request->user()->employee->branch_id;
    }

    private function assertSessionBranch(Request $request, int $bedId, int $employeeId): void
    {
        $bed = \App\Models\Bed::with('room')->findOrFail($bedId);
        $employee = Employee::findOrFail($employeeId);
        abort_unless((int) $bed->room->branch_id === (int) $employee->branch_id, 422, 'Therapist and bed must belong to the same branch.');
        $branchId = $this->staffBranchId($request);
        abort_unless($branchId === null || (int) $bed->room->branch_id === $branchId, 403, 'Session is outside your assigned branch.');
    }
}
