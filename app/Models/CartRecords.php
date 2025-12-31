<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartRecords extends Model
{
    protected $table = 'cart_records';
    
    protected $fillable = [
        'customer_id',
        'session_type',
        'session_date',
        'session_time',
        'employee_id',
        'treatment_id',
        'room_id',
        'quantity',
        'voucher_normal_quantity',
        'voucher_buy_quantity',
        'price'
    ];
    protected $guarded = ['id'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }   
}
