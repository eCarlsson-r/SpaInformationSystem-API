<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Conflict model for scheduling conflict records.
 *
 * Requirements: 6.1, 6.2, 8.1, 8.2, 8.3
 */
class Conflict extends Model
{
    use HasFactory;

    protected $table = 'conflicts';
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'conflicting_booking_id',
        'conflict_type',
        'detection_timestamp',
        'resolution_status',
        'resolution_action',
        'resolution_timestamp',
        'alternative_slots',
        'branch_id',
    ];

    protected $casts = [
        'alternative_slots'    => 'array',
        'detection_timestamp'  => 'datetime',
        'resolution_timestamp' => 'datetime',
    ];

    protected $attributes = [
        'resolution_status' => 'pending',
    ];

    public function booking()
    {
        return $this->belongsTo(Session::class, 'booking_id');
    }

    public function conflictingBooking()
    {
        return $this->belongsTo(Session::class, 'conflicting_booking_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
