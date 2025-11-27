<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    protected $table = 'sessions';

    protected $fillable = [
        'order_time',
        'reserved_time',
        'bed_id',
        'customer_id',
        'payment',
        'date',
        'start',
        'end',
        'status',
        'treatment_id',
        'employee_id'
    ];

    protected $guarded = [
        'id'
    ];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function bed()
    {
        return $this->belongsTo(Bed::class);
    }
}
