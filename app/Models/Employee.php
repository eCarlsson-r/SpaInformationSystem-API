<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'employees';

    protected $fillable = [
        'account_id',
        'complete_name',
        'nickname',
        'status',
        'identity_type',
        'identity_number',
        'pob', 'dob',
        'certified',
        'recruiter',
        'branch',
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
}
