<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;
    protected $table = 'agents';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'address',
        'city',
        'email',
        'phone',
        'mobile',
        'discount',
        'commission',
        'liability_account'
    ];

    protected $guarded = [
        'id'
    ];

    protected static function booted()
    {
        static::saved(fn () => event(new \App\Events\EntityUpdated('agents')));
        static::deleted(fn () => event(new \App\Events\EntityUpdated('agents')));
    }

    public function liability()
    {
        return $this->hasOne(Account::class, 'liability_account');
    }
}
