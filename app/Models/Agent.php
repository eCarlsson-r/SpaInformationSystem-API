<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    protected $table = 'agents';

    protected $fillable = [
        'name',
        'address',
        'city',
        'email',
        'phone',
        'mobile',
        'discount',
        'commission',
    ];

    protected $guarded = [
        'id'
    ];

    public function liability()
    {
        return $this->hasOne(Account::class, 'liability_account');
    }
}
