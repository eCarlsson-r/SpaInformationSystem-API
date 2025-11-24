<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAccount extends Model
{
    protected $table = 'cash_account';

    protected $fillable = [
        'name'
    ];

    protected $guarded = [
        'id'
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
