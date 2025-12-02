<?php

namespace Qisthidev\AuthDevice\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Qisthidev\AuthDevice\Traits\CanInvite;
use Qisthidev\AuthDevice\Traits\HasDevices;

class User extends Authenticatable
{
    use CanInvite, HasDevices, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];
}
