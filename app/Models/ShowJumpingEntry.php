<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowJumpingEntry extends Model
{
    protected $table = 'show_jumping_entries';

    protected $fillable = [
        'user_id',
        'cart_id',
        'rider_id',
        'horse_id',
        'event_id',
        'class_id',
        'season_code',
        'event_name',
        'status',
        'tran_ref',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        // ClassEntriesWeb stores these as varchar (e.g. E0007711, H000103).
        'rider_id' => 'string',
        'horse_id' => 'string',
        'event_id' => 'integer',
        'class_id' => 'integer',
        'season_code' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
