<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;
    protected $table = 'branches';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'address',
        'city',
        'country',
        'phone',
        'description',
        'cash_account',
        'walkin_account',
        'voucher_purchase_account',
        'voucher_usage_account',
        'branch_img'
    ];

    protected $attributes = [
        'city' => 'Medan',
        'country' => 'Indonesia',
        'description' => ''
    ];

    protected $guarded = [
        'id'
    ];

    protected static function booted()
    {
        static::saved(fn () => event(new \App\Events\EntityUpdated('branches')));
        static::deleted(fn () => event(new \App\Events\EntityUpdated('branches')));
    }

    public function room()
    {
        return $this->hasMany(Room::class);
    }

    public function employee()
    {
        return $this->hasMany(Employee::class);
    }

    public function sales()
    {
        return $this->hasMany(Sales::class);
    }
}
