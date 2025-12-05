<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalRecord extends Model
{
    use HasFactory;
    protected $table = 'journal_records';
    public $timestamps = false;
    
    protected $fillable = [
        'journal_id',
        'account_id',
        'debit',
        'credit',
        'description'
    ];

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
