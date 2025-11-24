<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $table = 'bank_account';
    protected $fillable = [
        'name',
        'gantung',
        'mesin',
        'kredit'
    ];

    protected $guarded = [
        'id'
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
