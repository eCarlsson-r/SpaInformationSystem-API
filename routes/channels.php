<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Authorize private-branch.{id} for staff with a matching branch assignment.
 *
 * Requirements: 6.4, 7.1
 */
Broadcast::channel('branch.{branchId}', function ($user, $branchId) {
    // Staff (non-customer) users with an employee record assigned to this branch
    if (strtoupper($user->type) === 'CUSTOMER') {
        return false;
    }

    $employee = $user->employee;

    return $employee && (int) $employee->branch_id === (int) $branchId;
});

/**
 * Authorize private-customer.{id} for the authenticated customer with matching ID.
 *
 * Requirements: 6.4, 7.1
 */
Broadcast::channel('customer.{customerId}', function ($user, $customerId) {
    $customer = $user->customer;

    return $customer && (int) $customer->id === (int) $customerId;
});
