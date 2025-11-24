<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $table = 'bank';
    public $incrementing = false;
    protected $fillable = [
        'name'
    ];

    protected $guarded = [
        'id'
    ];
}
