<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bed extends Model
{
    protected $table = 'beds';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
    ];

    protected $guarded = [
        'id'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
