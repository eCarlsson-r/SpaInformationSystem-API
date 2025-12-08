<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    protected $table = 'accounts';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'type',
    ];

    protected $guarded = [
        'id'
    ];

    public function discounts()
    {
        return $this->hasMany(Discount::class);
    }
}
