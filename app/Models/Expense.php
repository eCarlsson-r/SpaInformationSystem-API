<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Expense extends Model
{
    use HasFactory;
    protected $table = 'expenses';
    public $timestamps = false;

    protected $fillable = [
        'journal_reference',
        'date',
        'partner',
        'partner_type',
        'description'
    ];

    protected $guarded = [
        'id'
    ];

    public function journal()
    {
        return $this->belongsTo(Journal::class, 'journal_reference', 'reference');
    }

    public function items()
    {
        return $this->hasMany(ExpenseItem::class);
    }

    public function payments()
    {
        return $this->hasMany(ExpensePayment::class);
    }
}
