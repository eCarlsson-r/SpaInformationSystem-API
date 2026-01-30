<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model
{
    use HasFactory;
    protected $table = 'accounts';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'type',
        'category'
    ];

    protected $guarded = [
        'id'
    ];

    protected static function booted()
    {
        static::saved(fn () => event(new \App\Events\EntityUpdated('accounts')));
        static::deleted(fn () => event(new \App\Events\EntityUpdated('accounts')));
    }

    public function discounts()
    {
        return $this->hasMany(Discount::class);
    }
}
