<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Walkin extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    protected $table = 'walkin';
    public $timestamps = false;

    protected $fillable = [
        'treatment_id',
        'customer_id',
        'sales_id',
        'session_id'
    ];

    protected $guarded = [
        'id'
    ];

    // app/Models/Treatment.php
    protected static function booted()
    {
        static::saved(fn () => event(new \App\Events\EntityUpdated('walkins')));
        static::deleted(fn () => event(new \App\Events\EntityUpdated('walkins')));
    }

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sales()
    {
        return $this->belongsTo(Sales::class);
    }

    public function session()
    {
        return $this->belongsTo(Session::class);
    }
}
