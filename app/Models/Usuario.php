<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Usuario extends Authenticatable implements JWTSubject
{
    protected $table = 'usuarios';

    public $timestamps = false;

    protected $fillable = [
        'user_type_id', 'name', 'first_name', 'last_name',
        'username', 'email', 'password', 'status', 'date_creation',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'date_creation' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'username'  => $this->username,
            'user_type' => $this->user_type_id,
        ];
    }

    public function tipoUsuario()
    {
        return $this->belongsTo(TipoUsuario::class, 'user_type_id');
    }

    public function sesiones()
    {
        return $this->hasMany(Sesion::class, 'user_id');
    }
}
