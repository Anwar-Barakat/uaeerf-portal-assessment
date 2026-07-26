<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderRenewal extends Model
{
    protected $fillable = [
        'user_id',
        'cart_id',
        'rider_id',
        'season_id',
        'discipline_id',
        'weight',
        'jumping_division_level',
        'competition_level',
        'status',
        'tran_ref',
        'soap_response',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}
