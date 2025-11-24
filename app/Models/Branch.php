<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $table = 'branches';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'address',
        'city',
        'country',
        'phone',
        'description',
        'cash_account',
        'walkin_account',
        'purchase_voucher_account',
        'usage_voucher_account',
        'image'
    ];

    protected $guarded = [
        'id'
    ];

    public function room()
    {
        return $this->hasMany(Room::class);
    }
}
