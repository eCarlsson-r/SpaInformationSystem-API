<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomePayment extends Model
{
    protected $table = 'income_payments';

    protected $fillable = [
        'type',
        'tool',
        'amount',
        'description'
    ];

    protected $guarded = [
        'id'
    ];

    public function income()
    {
        return $this->belongsTo(Income::class);
    }
}
