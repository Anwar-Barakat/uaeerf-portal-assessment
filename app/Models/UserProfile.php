<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Federation web portal account.
 *
 * Maps to [EEF].[eefsys].[WebUserProfile]. There is no dbo.UserProfile on the
 * server - the assessment brief's table name does not exist. UserId is the
 * primary key and is NOT an identity column, so inserts must allocate it.
 *
 * @property int $UserId
 * @property string|null $Email
 * @property string|null $Password
 * @property string|null $Fname
 * @property string|null $Lname
 * @property string|null $MobileNo
 * @property string|null $Stable_ID
 * @property string|null $Discipline
 */
class UserProfile extends Model
{
    protected $connection = 'federation';

    protected $primaryKey = 'UserId';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'UserId',
        'Email',
        'Password',
        'Fname',
        'Lname',
        'MobileNo',
        'Dob',
        'Stable_ID',
        'Discipline',
        'UserGender',
        'IsStableAuthorized',
        'IsSynced',
        'DateRegistered',
        'AccountsCustomerID',
    ];

    protected $hidden = [
        'Password',
    ];

    protected $casts = [
        'UserId' => 'integer',
        'Dob' => 'datetime',
        'DateRegistered' => 'datetime',
        'IsStableAuthorized' => 'boolean',
        'IsSynced' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('services.federation.user_profile_table', 'eefsys.WebUserProfile');
    }

    /**
     * Reserve the next UserId. The column is the PK but has no identity or
     * sequence, so callers must supply it. Run inside a transaction.
     */
    public static function nextUserId(): int
    {
        $max = static::query()
            ->lockForUpdate()
            ->max('UserId');

        return ((int) $max) + 1;
    }

    /**
     * Legacy rows store the password in plain text (observed lengths 3-20).
     * Rows this application creates are bcrypt. Accept both on read, never
     * write plain text.
     */
    public function verifyPassword(string $plain): bool
    {
        $stored = (string) $this->Password;

        if ($stored === '') {
            return false;
        }

        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
            return Hash::check($plain, $stored);
        }

        return hash_equals($stored, $plain);
    }

    public function fullName(): string
    {
        return trim(($this->Fname ?? '') . ' ' . ($this->Lname ?? ''));
    }

    public static function findByEmail(string $email): ?self
    {
        return static::query()
            ->whereRaw('LOWER(Email) = ?', [mb_strtolower($email)])
            ->first();
    }

    /**
     * @template TReturn
     *
     * @param  \Closure(\Illuminate\Database\Connection): TReturn  $callback
     * @return TReturn
     */
    public static function transaction(\Closure $callback)
    {
        return DB::connection('federation')->transaction($callback);
    }
}
