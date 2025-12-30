<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    protected $table = 'bank';
    public $incrementing = false;
    public $timestamps = false;
    
    protected $fillable = [
        'id',
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
