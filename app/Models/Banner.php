<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Banner extends Model
{
    use HasFactory;
    protected $table = 'banners';
    public $timestamps = false;
    
    protected $fillable = [
        'name',
        'image',
        'introduction',
        'title',
        'subtitle',
        'description',
        'action',
        'action_page',
    ];

    protected $guarded = ['id'];

    // app/Models/Treatment.php
    protected static function booted()
    {
        static::saved(fn () => event(new \App\Events\EntityUpdated('banners')));
        static::deleted(fn () => event(new \App\Events\EntityUpdated('banners')));
    }
}
