<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\BookingPayment;
use App\Models\Employee;
use App\Models\Session;

class SessionSlotService
{
    public function lockResources(int $employeeId, int $bedId): void
    {
        Employee::whereKey($employeeId)->lockForUpdate()->firstOrFail();
        Bed::whereKey($bedId)->lockForUpdate()->firstOrFail();
    }

    public function hasConflict(
        int $employeeId,
        int $bedId,
        string $date,
        string $start,
        string $end,
        ?int $ignoreSessionId = null,
        ?int $ignorePaymentId = null,
    ): bool {
        $overlappingSessions = Session::query()
            ->whereDate('date', $date)
            ->whereIn('status', ['waiting', 'ongoing'])
            ->where('start', '<', $end)
            ->where('end', '>', $start)
            ->when($ignoreSessionId, fn ($query) => $query->whereKeyNot($ignoreSessionId));

        $bedConflict = (clone $overlappingSessions)->where('bed_id', $bedId)->exists();
        $employeeConflict = (clone $overlappingSessions)->where('employee_id', $employeeId)->exists();

        $overlappingPayments = BookingPayment::query()
            ->whereDate('session_date', $date)
            ->whereIn('status', ['creating', 'pending'])
            ->where('expires_at', '>', now())
            ->where('session_start', '<', $end)
            ->where('session_end', '>', $start)
            ->when($ignorePaymentId, fn ($query) => $query->whereKeyNot($ignorePaymentId));

        return $bedConflict
            || $employeeConflict
            || (clone $overlappingPayments)->where('bed_id', $bedId)->exists()
            || (clone $overlappingPayments)->where('employee_id', $employeeId)->exists();
    }

    public function assertAvailable(
        int $employeeId,
        int $bedId,
        string $date,
        string $start,
        string $end,
        ?int $ignoreSessionId = null,
        ?int $ignorePaymentId = null,
    ): void {
        abort_if(
            $this->hasConflict($employeeId, $bedId, $date, $start, $end, $ignoreSessionId, $ignorePaymentId),
            409,
            'The selected therapist or bed is no longer available for that time.'
        );
    }
}