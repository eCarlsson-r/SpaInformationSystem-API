<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'employees';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'complete_name',
        'nickname',
        'status',
        'identity_type',
        'identity_number',
        'pob', 'dob',
        'certified',
        'recruiter',
        'base_salary',
        'expertise',
        'gender', 'phone',
        'address', 'mobile', 'email',
        'absent_deduction',
        'meal_fee',
        'late_deduction',
        'bank_account',
        'bank'
    ];

    protected $guarded = [
        'id'
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'user_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function sessions()
    {
        return $this->hasMany(Session::class);
    }

    public function grade()
    {
        return $this->hasMany(Grade::class);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }
}
