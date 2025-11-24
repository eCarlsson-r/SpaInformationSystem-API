<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customers';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'gender',
        'address',
        'city',
        'country',
        'place_of_birth',
        'date_of_birth',
        'mobile',
        'email'
    ];

    protected $guarded = [
        'id'
    ];

    public function liability()
    {
        return $this->hasOne(Account::class, 'liability_account');
    }
}
