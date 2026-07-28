<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class Account extends Authenticatable
{
    use Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'accounts';

    protected $guarded = [];

    protected $hidden = [
        'email_digest',
        'email_encrypted',
        'remember_token',
    ];
}
