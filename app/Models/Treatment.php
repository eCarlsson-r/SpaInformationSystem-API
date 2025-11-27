<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
{
    protected $table = 'treatments';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'price',
        'duration',
        'room',
        'category_id',
        'applicable_days',
        'applicable_time_start',
        'applicable_time_end',
        'voucher',
        'image',
        'video',
        'thumbnail',
        'minimum_quantity'
    ];

    protected $guarded = [
        'id'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function bonus()
    {
        return $this->hasMany(Bonus::class, 'treatment_id');
    }
}
