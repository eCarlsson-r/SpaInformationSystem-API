<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'suppliers';

    protected $fillable = [
        'name',
        'contact',
        'bank',
        'bank_account',
        'room',
        'address',
        'mobile',
        'email'
    ];

    protected $guarded = [
        'id'
    ];
}
