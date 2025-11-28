<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $table = 'voucher';
    protected $incrementing = false;

    protected $fillable = [
        'register_date',
        'register_time',
        'amount',
        'purchase_date'
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
