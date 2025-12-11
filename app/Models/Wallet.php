<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    protected $table = 'wallets';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'bank_account_number',
        'bank_id',
        'account_id',
        'edc_machine'
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
