<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Treatment extends Model
{
    use HasFactory;
    protected $table = 'treatments';
    public $timestamps = false;

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
        'voucher_normal_quantity',
        'voucher_purchase_quantity',
        'minimum_quantity',
        'body_img',
        'icon_img'
    ];

    protected $guarded = [
        'id'
    ];

    protected static function booted()
    {
        static::saved(fn () => event(new \App\Events\EntityUpdated('treatments')));
        static::deleted(fn () => event(new \App\Events\EntityUpdated('treatments')));
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function bonus()
    {
        return $this->hasMany(Bonus::class, 'treatment_id');
    }
}
