<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Voucher extends Model
{
    use HasFactory;
    protected $table = 'voucher';
    public $incrementing = false;
    public $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'treatment_id',
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
