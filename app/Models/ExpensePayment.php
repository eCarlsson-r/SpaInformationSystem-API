<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpensePayment extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    protected $table = 'expense_payments';
    public $timestamps = false;

    protected $fillable = [
        'expense_id',
        'type',
        'wallet_id',
        'amount',
        'description'
    ];

    protected $guarded = [
        'id'
    ];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
