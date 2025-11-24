<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Walkin extends Model
{
    protected $table = 'walkin';

    protected $fillable = [
        'treatment_id',
        'customer_id',
        'sales_id',
        'session_id'
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

    public function sales()
    {
        return $this->belongsTo(Sales::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
    }
}
