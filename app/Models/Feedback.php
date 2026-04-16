<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Feedback model for post-session customer feedback.
 *
 * Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 10.1
 */
class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedbacks';
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'customer_id',
        'rating',
        'comment',
        'sentiment_score',
        'sentiment_label',
        'analysis_status',
        'analysis_attempts',
        'submitted_at',
        'analyzed_at',
    ];

    protected $casts = [
        'sentiment_score'   => 'float',
        'analysis_attempts' => 'integer',
        'submitted_at'      => 'datetime',
        'analyzed_at'       => 'datetime',
    ];

    protected $attributes = [
        'analysis_status'   => 'pending',
        'analysis_attempts' => 0,
    ];

    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
