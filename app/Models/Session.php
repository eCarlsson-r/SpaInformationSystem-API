<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    protected $table = 'session';
    protected $primaryKey = 'session-code';

    protected $fillable = [
        'order-time',
        'reserved-time',
        'session-bed',
        'session-customer',
        'session-payment',
        'session-date',
        'session-start',
        'session-end',
        'session-status',
        'session-treatment',
        'session-therapist',
        'session-testimony'
    ];

    protected $guarded = [
        'session-code'
    ];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function therapist()
    {
        return $this->belongsTo(Employee::class);
    }

    public function bed()
    {
        return $this->belongsTo(Bed::class);
    }
}
