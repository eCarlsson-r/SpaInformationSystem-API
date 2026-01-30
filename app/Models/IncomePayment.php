<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IncomePayment extends Model
{
    use HasFactory;
    protected $table = 'income_payments';
    public $timestamps = false;
    
    protected $fillable = [
        'income_id',
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
