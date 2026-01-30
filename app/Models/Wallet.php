<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Wallet extends Model
{
    use HasFactory;
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

    // app/Models/Treatment.php
    protected static function booted()
    {
        static::saved(fn () => event(new \App\Events\EntityUpdated('wallets')));
        static::deleted(fn () => event(new \App\Events\EntityUpdated('wallets')));
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
