<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    protected $table = 'categories';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
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

    public function treatment()
    {
        return $this->hasMany(Treatment::class, 'category_id');
    }
}
