<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'tbluser';

    protected $fillable = [
        'username',
        'email',
        'google_id',
        'password',
        'role',
        'profile_picture',
        'first_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'first_login' => 'boolean',
        'google_id' => 'boolean',
    ];

    /**
     * Check if the user is an admin or HR
     */
    public function isHR(): bool
    {
        return in_array($this->role, ['SuperAdmin', 'SubAdmin', 'hr']);
    }
}
