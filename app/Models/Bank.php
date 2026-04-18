<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bank extends Model
{
    use HasFactory;
    protected $table = 'bank';
    public $timestamps = false;
    
    protected $fillable = [
        'code',
        'name'
    ];

    protected $guarded = [
        'id'
    ];

    protected static function booted()
    {
        static::saved(fn () => event(new \App\Events\EntityUpdated('banks')));
        static::deleted(fn () => event(new \App\Events\EntityUpdated('banks')));
    }
}
