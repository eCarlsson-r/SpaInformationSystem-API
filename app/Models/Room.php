<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'rooms';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'image'
    ];

    protected $guarded = [
        'id'
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function bed()
    {
        return $this->hasMany(Bed::class, 'room_id');
    }
}
