<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Period extends Model
{
    protected $table = 'periods';
    public $timestamps = false;

    protected $fillable = [
        'expense_id',
        'start',
        'end',
    ];

    protected $guarded = [
        'id'
    ];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function compensations()
    {
        return $this->hasMany(Compensation::class);
    }
}
