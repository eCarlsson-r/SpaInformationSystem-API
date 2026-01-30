<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Shift extends Model
{
    use HasFactory;
    protected $table = 'shifts';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'name',
        'start_time',
        'end_time'
    ];

    protected $guarded = [
        'id'
    ];

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }
}
