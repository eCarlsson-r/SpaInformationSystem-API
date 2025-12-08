<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    protected $table = 'sessions';
    public $timestamps = false;

    protected $fillable = [
        'order_time',
        'reserved_time',
        'payment',
        'date',
        'start',
        'end',
        'status'
    ];

    protected $guarded = [
        'id'
    ];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function bed()
    {
        return $this->belongsTo(Bed::class);
    }

    public function walkin()
    {
        return $this->hasOne(Walkin::class);
    }

    public function voucher()
    {
        return $this->hasOne(Voucher::class);
    }
}
