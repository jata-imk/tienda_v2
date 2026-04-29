<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sesion extends Model
{
    protected $table = 'sesiones';

    public $timestamps = false;

    protected $fillable = [
        'session', 'user_id', 'token_id', 'status', 'date_start', 'date_end',
    ];

    protected $casts = [
        'session'    => 'array',
        'date_start' => 'datetime',
        'date_end'   => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'user_id');
    }

    public function token()
    {
        return $this->belongsTo(Token::class, 'token_id');
    }
}
