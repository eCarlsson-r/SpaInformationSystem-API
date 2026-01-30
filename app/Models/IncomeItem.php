<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IncomeItem extends Model
{
    use HasFactory;
    protected $table = 'income_items';
    public $timestamps = false;
    
    protected $fillable = [
        'income_id',
        'type',
        'transaction',
        'amount',
        'description'
    ];

    protected $guarded = [
        'id'
    ];

    public function income()
    {
        return $this->belongsTo(Income::class);
    }
}
