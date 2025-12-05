<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bed extends Model
{
    use HasFactory;
    protected $table = 'beds';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'room_id'
    ];

    protected $guarded = [
        'id'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
