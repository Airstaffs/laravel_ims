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
        'password',
        'role',
        'company',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
