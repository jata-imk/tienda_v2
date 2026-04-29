<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    protected $table = 'tokens';

    public $timestamps = false;

    protected $fillable = ['status', 'token', 'date_start', 'date_expiration'];

    protected $casts = [
        'date_start'      => 'datetime',
        'date_expiration' => 'datetime',
    ];

    public function sesion()
    {
        return $this->hasOne(Sesion::class, 'token_id');
    }
}
