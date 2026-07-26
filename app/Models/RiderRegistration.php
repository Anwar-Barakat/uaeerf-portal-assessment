<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderRegistration extends Model
{
    protected $fillable = [
        'user_id',
        'cart_id',
        'rider_name',
        'first_name',
        'last_name',
        'date_of_birth',
        'nationality',
        'gender_id',
        'nationality_id',
        'city_id',
        'country_id',
        'email',
        'mobile',
        'address',
        'po_box',
        'weight',
        'visa_category',
        'eid',
        'register_season',
        'register_fei',
        'passport_number',
        'discipline_id',
        'category_id',
        'status',
        'tran_ref',
        'soap_response',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'completed_at' => 'datetime',
    ];
}
