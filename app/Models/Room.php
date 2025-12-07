<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;
    protected $table = 'rooms';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'image',
        'branch_id'
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
