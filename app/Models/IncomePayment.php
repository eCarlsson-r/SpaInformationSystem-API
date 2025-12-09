<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomePayment extends Model
{
    protected $table = 'income_payments';
    public $timestamps = false;
    
    protected $fillable = [
        'type',
        'wallet_id',
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

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
