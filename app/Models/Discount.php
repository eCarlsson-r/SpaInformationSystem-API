<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $table = 'discounts';
    public $incrementing = false;
    public $timestamps = false;
    
    protected $fillable = [
        'name',
        'type',
        'percent',
        'amount',
        'quantity',
        'expiry_date',
        'account_id'
    ];
    protected $guarded = ['id'];

    protected $casts = [
        'expiry_date' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
