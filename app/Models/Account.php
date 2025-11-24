<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $table = 'accounts';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'type',
    ];

    protected $guarded = [
        'id'
    ];
}
