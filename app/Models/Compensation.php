<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compensation extends Model
{
    protected $table = 'compensations';
    public $timestamps = false;
    
    protected $fillable = [
        'employee_id',
        'period_id',
        'base_salary',
        'therapist_bonus',
        'recruit_bonus',
        'addition',
        'addition_description',
        'deduction',
        'deduction_description',
        'total',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function period()
    {
        return $this->belongsTo(Period::class);
    }
}
