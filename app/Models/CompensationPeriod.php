<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompensationPeriod extends Model
{
    protected $table = 'periods';

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
}
