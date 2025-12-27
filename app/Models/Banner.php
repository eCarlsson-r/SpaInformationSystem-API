<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
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
}
