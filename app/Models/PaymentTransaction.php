<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'tran_ref',
        'cart_id',
        'amount',
        'currency',
        'status',
        'response_code',
        'response_message',
        'webhook_payload',
        'processed',
        'processed_at',
    ];

    protected $casts = [
        'processed' => 'boolean',
        'processed_at' => 'datetime',
    ];
}
