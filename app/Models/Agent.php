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
    ];

    protected $guarded = [
        'id'
    ];

    public function liability()
    {
        return $this->hasOne(Account::class, 'liability_account');
    }
}
