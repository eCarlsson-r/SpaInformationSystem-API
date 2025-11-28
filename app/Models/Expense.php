<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $table = 'expense';

    protected $fillable = [
        'bkk',
        'date',
        'partner',
        'partner_type',
        'description'
    ];

    protected $guarded = [
        'id'
    ];

    public function items()
    {
        return $this->hasMany(ExpenseItem::class);
    }

    public function payments()
    {
        return $this->hasMany(ExpensePayment::class);
    }
}
