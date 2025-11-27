<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
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
