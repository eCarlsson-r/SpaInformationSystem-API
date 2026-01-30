<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sales extends Model
{
    use HasFactory;
    protected $table = 'sales';
    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'date', 
        'time', 
        'subtotal',
        'discount', 
        'rounding', 
        'total',
        'income_id',
        'employee_id'
    ];

    protected $guarded = [
        'id'
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function income()
    {
        return $this->belongsTo(Income::class);
    }

    public function records()
    {
        return $this->hasMany(SalesRecord::class);
    }
}
