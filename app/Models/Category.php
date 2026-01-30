<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;
    protected $table = 'categories';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'description',
        'i18n',
        'header_img',
        'body_img1',
        'body_img2',
        'body_img3'
    ];

    protected $guarded = [
        'id'
    ];

    // app/Models/Treatment.php
    protected static function booted()
    {
        static::saved(fn () => event(new \App\Events\EntityUpdated('categories')));
        static::deleted(fn () => event(new \App\Events\EntityUpdated('categories')));
    }

    public function treatment()
    {
        return $this->hasMany(Treatment::class, 'category_id');
    }
}
