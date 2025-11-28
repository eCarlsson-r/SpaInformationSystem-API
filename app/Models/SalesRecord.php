<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesRecord extends Model
{
    protected $table = 'sales_records';

    protected $fillable = [
        'quantity',
        'price',
        'discount',
        'redeem_type',
        'voucher_start',
        'voucher_end',
        'total_price',
        'description'
    ];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function sales()
    {
        return $this->belongsTo(Sales::class);
    }
}
