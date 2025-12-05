<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    use HasFactory;
    protected $table = 'journals';
    public $timestamps = false;
    
    protected $fillable = [
        'reference',
        'date',
        'description',
    ];

    public function records()
    {
        return $this->hasMany(JournalRecord::class);
    }

    public function expenses()
    {
        return $this->hasOne(Expense::class, 'journal_reference', 'reference');
    }

    public function incomes()
    {
        return $this->hasOne(Income::class, 'journal_reference', 'reference');
    }

    public function transfers()
    {
        return $this->hasOne(Transfer::class, 'journal_reference', 'reference');
    }
}
