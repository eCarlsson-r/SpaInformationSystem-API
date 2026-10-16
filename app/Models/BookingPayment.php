<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingPayment extends Model
{
    protected $fillable = [
        'customer_id', 'branch_id', 'cart_record_id', 'treatment_id', 'employee_id', 'room_id', 'bed_id',
        'session_date', 'session_start', 'session_end', 'amount', 'reference_id', 'gateway_session_id',
        'payment_link_url', 'gateway_payment_id', 'status', 'expires_at', 'sales_id', 'session_id',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function treatment() { return $this->belongsTo(Treatment::class); }
    public function session() { return $this->belongsTo(Session::class); }
}
