<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    protected $table = 'customers';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'gender',
        'address',
        'city',
        'country',
        'place_of_birth',
        'date_of_birth',
        'mobile',
        'email',
        'liability_account'
    ];

    protected $guarded = [
        'id'
    ];

    // app/Models/Treatment.php
    protected static function booted()
    {
        static::saved(fn () => event(new \App\Events\EntityUpdated('customers')));
        static::deleted(fn () => event(new \App\Events\EntityUpdated('customers')));
    }

    public function liability()
    {
        return $this->hasOne(Account::class, 'liability_account');
    }
}
